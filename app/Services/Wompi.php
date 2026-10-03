<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API de Wompi El Salvador (https://api.wompi.sv).
 * Solo lo que usa Clínea: enlaces de pago recurrente y sus suscripciones.
 */
class Wompi
{
    // Valores del enum EstadoSuscripcion. Wompi no documenta qué significa
    // cada uno; por defecto el listado filtra "Activa", así que se consulta
    // estado por estado para no perder las que dejaron de estar activas.
    public const ESTADOS = [0, 1, 2, 3, 4];

    public function configurado(): bool
    {
        return filled(config('services.wompi.app_id')) && filled(config('services.wompi.api_secret'));
    }

    /**
     * Crea un enlace recurrente y devuelve ['id' => ..., 'url' => ...].
     */
    public function crearEnlaceRecurrente(string $nombre, float $monto, int $diaDePago, string $descripcion): array
    {
        $r = $this->api()->post('/EnlacePagoRecurrente', [
            'idAplicativo' => config('services.wompi.app_id'),
            'nombre' => mb_substr($nombre, 0, 200),
            'monto' => round($monto, 2),
            'diaDePago' => $diaDePago,
            'descripcionProducto' => mb_substr($descripcion, 0, 3000),
        ])->throw()->json();

        if (empty($r['idEnlace']) || empty($r['urlEnlace'])) {
            throw new RuntimeException('Wompi no devolvió el enlace: '.json_encode($r));
        }

        return ['id' => (string) $r['idEnlace'], 'url' => $r['urlEnlace'], 'productivo' => (bool) ($r['estaProductivo'] ?? false)];
    }

    /**
     * Todas las suscripciones de un enlace, en cualquier estado.
     * Cada una trae: id, alias, monto, pagosRealizados, estado, idSuscriptor,
     * nombreSuscriptor, fechaInicio, diaPago, fechaCreacion.
     */
    public function suscripciones(string $enlaceId): array
    {
        $todas = [];
        foreach (self::ESTADOS as $estado) {
            $pagina = 1;
            do {
                $lote = $this->api()->get('/EnlacePagoRecurrente/'.rawurlencode($enlaceId).'/suscripciones', [
                    'Estado' => $estado,
                    'PaginaActual' => $pagina,
                    'SuscripcionesPorPagina' => 50,
                ])->throw()->json() ?? [];
                foreach ($lote as $s) {
                    $todas[$s['id'] ?? uniqid()] = $s;
                }
                $pagina++;
            } while (count($lote) === 50);
        }

        return array_values($todas);
    }

    public function desactivarEnlace(string $enlaceId): void
    {
        $this->api()->post('/EnlacePagoRecurrente/'.rawurlencode($enlaceId))->throw();
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.wompi.api_url'), '/'))
            ->withToken($this->token())
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    private function token(): string
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Faltan WOMPI_APP_ID / WOMPI_API_SECRET en el .env');
        }

        return Cache::remember('wompi.token', now()->addMinutes(50), function () {
            $r = Http::asForm()->timeout(20)->post(config('services.wompi.token_url'), [
                'grant_type' => 'client_credentials',
                'audience' => 'wompi_api',
                'client_id' => config('services.wompi.app_id'),
                'client_secret' => config('services.wompi.api_secret'),
            ])->throw()->json();

            if (empty($r['access_token'])) {
                throw new RuntimeException('Wompi no devolvió access_token');
            }

            return $r['access_token'];
        });
    }
}
