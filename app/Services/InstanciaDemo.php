<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pide a la instancia demo de Clinea (demo.clinea.app) un acceso de 24 horas.
 * Del otro lado: App\Http\Controllers\Api\AccesoDemoController del EMR.
 */
class InstanciaDemo
{
    public const CUENTA_EXISTENTE = 'cuenta_existente';

    public function configurada(): bool
    {
        return filled(config('clinea.demo.token'));
    }

    /**
     * @return array{url: string, correo: string, password: string, expira_en: string, nuevo: bool}
     *
     * @throws RuntimeException con el mensaje CUENTA_EXISTENTE si el correo es de una cuenta real
     */
    public function crearAcceso(string $nombre, string $correo, string $especialidad): array
    {
        if (! $this->configurada()) {
            throw new RuntimeException('Falta CLINEA_DEMO_API_TOKEN en el .env');
        }

        try {
            $r = Http::baseUrl(rtrim(config('clinea.demo.url'), '/'))
                ->withToken(config('clinea.demo.token'))
                ->acceptJson()
                ->timeout(20)
                ->post('/api/accesos-demo', [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'especialidad' => $especialidad,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 409) {
                throw new RuntimeException(self::CUENTA_EXISTENTE);
            }
            throw $e;
        }

        if (empty($r['password']) || empty($r['url']) || empty($r['expira_en'])) {
            throw new RuntimeException('La instancia demo no devolvió el acceso: '.json_encode(array_keys($r ?? [])));
        }

        return $r;
    }
}
