<?php

namespace App\Services;

use App\Mail\Aviso;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Avisos
{
    private const TITULOS = [
        'solicitud' => 'Nueva solicitud — fue a pagar',
        'primer_pago' => 'Primer pago — hay que activar la clínica',
        'pago' => 'Pago mensual recibido',
        'atraso' => 'No se registró el cobro del mes',
        'varias' => 'Revisar: el enlace tiene más de una suscripción',
        'error' => 'No se pudo consultar en Wompi',
    ];

    public function solicitud(Suscripcion $s): void
    {
        $this->enviar('Clínea: nueva solicitud de '.$s->clinica, [['tipo' => 'solicitud', 'suscripcion' => $s]]);
    }

    /** @param array<int, array{tipo: string, suscripcion: Suscripcion, detalle?: string}> $eventos */
    public function resumen(array $eventos): void
    {
        if (! $eventos) {
            return;
        }
        $hay = fn ($t) => collect($eventos)->contains('tipo', $t);
        $asunto = match (true) {
            $hay('primer_pago') => 'Clínea: ¡nuevo cliente pagó! Activar clínica',
            $hay('atraso') => 'Clínea: hay suscripciones atrasadas',
            default => 'Clínea: movimientos en suscripciones',
        };
        $this->enviar($asunto, $eventos);
    }

    private function enviar(string $asunto, array $eventos): void
    {
        $bloques = array_map(function ($e) {
            $s = $e['suscripcion'];

            return [
                'titulo' => (self::TITULOS[$e['tipo']] ?? $e['tipo']).': '.$s->clinica,
                'lineas' => array_values(array_filter([
                    $s->nombre_contacto.' · '.$s->email.' · '.$s->whatsapp,
                    $s->nombrePlan().' · '.$s->nombrePais().' · $'.number_format((float) $s->monto, 2).'/mes (cobra el día '.$s->dia_cobro.')',
                    $e['detalle'] ?? null,
                ])),
                'url' => url('/cuentas/'.$s->id),
            ];
        }, $eventos);

        try {
            Mail::to(config('clinea.avisos_a'))->send(new Aviso($asunto, $bloques));
        } catch (Throwable $e) {
            // Que un correo caído no tumbe la contratación ni la revisión.
            Log::error('No se pudo enviar el aviso: '.$asunto, ['error' => $e->getMessage()]);
        }
    }
}
