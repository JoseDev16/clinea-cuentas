<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\PagoWompi;
use App\Models\Suscripcion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pagos de Honduras y Guatemala: un enlace fijo de Wompi por país, de un solo
 * pago (un mes). Cada pago se registra una vez, aunque llegue por el webhook y
 * por la revisión de respaldo, y dispara dos correos: el aviso al equipo y la
 * bienvenida al cliente con los pasos a seguir.
 */
class PagosUnicos
{
    public function __construct(private Wompi $wompi, private Avisos $avisos, private Bienvenidas $bienvenidas) {}

    /** País del enlace, o null si no es uno de los enlaces de pago único. */
    public function paisDelEnlace(string|int|null $enlaceId): ?string
    {
        foreach (config('clinea.pago_unico') as $pais => $e) {
            if ((string) $e['enlace_id'] === (string) $enlaceId) {
                return $pais;
            }
        }

        return null;
    }

    /**
     * El cuerpo del webhook (ya con la firma verificada). Devuelve el pago
     * registrado, o null si no es de nuestros enlaces o no fue aprobado.
     */
    public function desdeWebhook(array $w): ?PagoWompi
    {
        $pais = $this->paisDelEnlace(data_get($w, 'EnlacePago.Id'));
        if (! $pais || strcasecmp((string) ($w['ResultadoTransaccion'] ?? ''), 'ExitosaAprobada') !== 0) {
            return null;
        }
        $cliente = $w['cliente'] ?? $w['Cliente'] ?? [];

        return $this->registrar([
            'id_transaccion' => (string) $w['IdTransaccion'],
            'enlace_id' => (string) data_get($w, 'EnlacePago.Id'),
            'pais' => $pais,
            'monto' => (float) ($w['Monto'] ?? 0),
            'cliente_nombre' => $cliente['Nombre'] ?? null,
            'cliente_email' => isset($cliente['Email']) ? mb_strtolower(trim($cliente['Email'])) : null,
            'pagado_at' => isset($w['FechaTransaccion']) ? Carbon::parse($w['FechaTransaccion']) : now(),
            'origen' => 'webhook',
            'datos' => $w,
        ]);
    }

    /**
     * Respaldo del webhook: pregunta a Wompi por las transacciones de cada
     * enlace y registra las aprobadas que no conocíamos. Deja pasar unos
     * minutos para que el webhook (que trae el correo del cliente) llegue antes.
     *
     * @return array<int, PagoWompi>
     */
    public function revisar(): array
    {
        $nuevos = [];
        foreach (config('clinea.pago_unico') as $pais => $e) {
            try {
                $enlace = $this->wompi->enlacePago((string) $e['enlace_id']);
            } catch (Throwable $ex) {
                Log::warning("No se pudo consultar el enlace de pago de {$pais} en Wompi", ['error' => $ex->getMessage()]);

                continue;
            }
            foreach ($enlace['transacciones'] ?? [] as $t) {
                $id = (string) ($t['idTransaccion'] ?? '');
                if ($id === '' || ! ($t['esAprobada'] ?? false) || ! ($t['esReal'] ?? false)) {
                    continue;
                }
                $fecha = isset($t['fechaTransaccion']) ? Carbon::parse($t['fechaTransaccion']) : now();
                if ($fecha->gt(now()->subMinutes(config('clinea.minutos_espera_webhook')))
                    || PagoWompi::where('id_transaccion', $id)->exists()) {
                    continue;
                }
                if ($p = $this->registrar([
                    'id_transaccion' => $id,
                    'enlace_id' => (string) $e['enlace_id'],
                    'pais' => $pais,
                    'monto' => (float) ($t['monto'] ?? 0),
                    'pagado_at' => $fecha,
                    'origen' => 'revision',
                    'datos' => $t,
                ])) {
                    $nuevos[] = $p;
                }
            }
        }

        return $nuevos;
    }

    /** Registra el pago una sola vez y manda los dos correos. */
    public function registrar(array $datos): ?PagoWompi
    {
        try {
            $pago = DB::transaction(function () use ($datos) {
                $pago = PagoWompi::create($datos);
                [$s, $porCorreo] = $this->suscripcionPara($pago);

                $s->forceFill([
                    'estado' => 'suscrita',
                    'suscrita_at' => $s->suscrita_at ?? now(),
                    'primer_pago_at' => $s->primer_pago_at ?? $pago->pagado_at,
                    'ultimo_pago_at' => $pago->pagado_at,
                    'pagos_realizados' => $s->pagos_realizados + 1,
                    'wompi_nombre_suscriptor' => $pago->cliente_nombre ?? $s->wompi_nombre_suscriptor,
                ])->save();
                Pago::create([
                    'suscripcion_id' => $s->id,
                    'numero' => $s->pagos_realizados,
                    'monto' => $pago->monto,
                    'detectado_at' => now(),
                ]);
                $pago->forceFill(['suscripcion_id' => $s->id, 'coincidio_por_correo' => $porCorreo])->save();

                return $pago;
            });
        } catch (UniqueConstraintViolationException) {
            // Ya llegó por el otro camino (webhook o revisión): nada que hacer.
            return null;
        }

        $s = $pago->suscripcion;
        // Primero el aviso al equipo: es lo que no puede fallar.
        $this->avisos->pagoUnico($pago);
        $pago->forceFill(['aviso_enviado_at' => now()])->save();
        if ($s->email) {
            $this->bienvenidas->enviar($s);
        }

        return $pago;
    }

    /**
     * ¿De quién es el pago? 1) la solicitud pendiente del mismo país con el
     * mismo correo que escribió en Wompi; 2) la solicitud pendiente más reciente
     * de ese país (últimos 3 días); 3) si pagó sin pasar por el formulario, se
     * crea la ficha con lo que dio en Wompi. Devuelve [suscripción, por_correo].
     */
    private function suscripcionPara(PagoWompi $pago): array
    {
        $pendientes = Suscripcion::query()
            ->where('tipo', 'unico')
            ->where('pais', $pago->pais)
            ->where('estado', 'pendiente')
            ->where('created_at', '>=', now()->subDays(3))
            ->latest();

        if ($pago->cliente_email && ($s = (clone $pendientes)->where('email', $pago->cliente_email)->first())) {
            return [$s, true];
        }
        if ($s = $pendientes->first()) {
            return [$s, false];
        }

        $config = config("clinea.pago_unico.{$pago->pais}");

        return [Suscripcion::create([
            'pais' => $pago->pais,
            'plan' => 'whatsapp',
            'tipo' => 'unico',
            'monto' => $pago->monto ?: $config['monto'],
            'dia_cobro' => min(now()->day, config('clinea.dia_cobro_maximo')),
            'nombre_contacto' => $pago->cliente_nombre ?: 'Cliente de Wompi',
            'clinica' => $pago->cliente_nombre ?: 'Pagó directo en Wompi',
            'email' => (string) $pago->cliente_email,
            'whatsapp' => '',
            'wompi_url' => $config['url'],
            'notas' => 'Pagó en el enlace de Wompi sin llenar el formulario de Clinea: confirmar datos de la clínica por WhatsApp/correo.',
        ]), false];
    }
}
