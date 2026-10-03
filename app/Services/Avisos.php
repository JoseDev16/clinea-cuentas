<?php

namespace App\Services;

use App\Mail\Aviso;
use App\Models\SolicitudDemo;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Avisos
{
    private const TITULOS = [
        'solicitud' => 'Nueva solicitud — fue a pagar',
        'suscripcion' => 'Se suscribió — preparar su instancia',
        'primer_pago' => 'Primer pago — hay que activar la clínica',
        'pago' => 'Pago mensual recibido',
        'atraso' => 'No se registró el cobro del mes',
        'varias' => 'Revisar: el enlace tiene más de una suscripción',
        'error' => 'No se pudo consultar en Wompi',
    ];

    public function solicitud(Suscripcion $s): void
    {
        $this->enviar('Clinea: nueva solicitud de '.$s->clinica, [['tipo' => 'solicitud', 'suscripcion' => $s]]);
    }

    /** Un doctor pidió la demo de 24 horas desde la landing: es un lead. */
    public function demo(SolicitudDemo $d): void
    {
        $asunto = $d->estado === 'enviada'
            ? 'Clinea: nueva demo — '.$d->nombre.' ('.$d->especialidad.')'
            : 'Clinea: no se pudo entregar una demo — '.$d->nombre;

        $this->mandar($asunto, [[
            'titulo' => $d->etiquetaEstado().': '.$d->nombre,
            'lineas' => array_values(array_filter([
                $d->especialidad.' · '.$d->email.($d->pais ? ' · '.$d->pais : ''),
                $d->demo_expira_at ? 'Su acceso vence el '.$d->demo_expira_at->format('d/m/Y H:i') : null,
                $d->detalle,
            ])),
            'url' => url('/cuentas/demos'),
        ]]);
    }

    /** @param array<int, array{tipo: string, suscripcion: Suscripcion, detalle?: string}> $eventos */
    public function resumen(array $eventos): void
    {
        if (! $eventos) {
            return;
        }
        $hay = fn ($t) => collect($eventos)->contains('tipo', $t);
        $asunto = match (true) {
            $hay('suscripcion') => 'Clinea: ¡nuevo cliente se suscribió! Preparar su instancia (ya le llegó la bienvenida)',
            $hay('primer_pago') => 'Clinea: ¡nuevo cliente pagó! Activar clínica',
            $hay('atraso') => 'Clinea: hay suscripciones atrasadas',
            default => 'Clinea: movimientos en suscripciones',
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

        $this->mandar($asunto, $bloques);
    }

    private function mandar(string $asunto, array $bloques): void
    {
        try {
            Mail::to(config('clinea.avisos_a'))->send(new Aviso($asunto, $bloques));
        } catch (Throwable $e) {
            // Que un correo caído no tumbe la contratación ni la revisión.
            Log::error('No se pudo enviar el aviso: '.$asunto, ['error' => $e->getMessage()]);
        }
    }
}
