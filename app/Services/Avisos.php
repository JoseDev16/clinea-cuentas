<?php

namespace App\Services;

use App\Mail\Aviso;
use App\Models\PagoWompi;
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

    /** Contratar en Honduras / Guatemala: llenó el formulario y fue al enlace de Wompi. */
    public function solicitudUnica(Suscripcion $s): void
    {
        $this->mandar('Clinea: '.$s->clinica.' fue a pagar ('.$s->nombrePais().')', [[
            'titulo' => 'Fue a pagar el mes en Wompi: '.$s->clinica,
            'lineas' => [
                $s->nombre_contacto.' · '.$s->email.' · '.$s->whatsapp,
                config("clinea.pago_unico.{$s->pais}.anunciado").' (US$'.number_format((float) $s->monto, 2).') · 1 mes · '.$s->nombrePais(),
                'Todavía no ha pagado: te llega otro correo en cuanto Wompi confirme el pago.',
            ],
            'url' => url('/cuentas/'.$s->id),
        ]]);
    }

    /** Pago confirmado en un enlace de pago único (Honduras / Guatemala). */
    public function pagoUnico(PagoWompi $p): void
    {
        $s = $p->suscripcion;
        $this->mandar('Clinea: ¡PAGO RECIBIDO! '.$s->clinica.' ('.$s->nombrePais().') — US$'.number_format((float) $p->monto, 2), [[
            'titulo' => 'Pagó 1 mes de Clinea: '.$s->clinica,
            'lineas' => array_values(array_filter([
                'Monto: US$'.number_format((float) $p->monto, 2).' ('.config("clinea.pago_unico.{$s->pais}.anunciado").') · '.$s->nombrePais().' · '.$p->pagado_at?->format('d/m/Y H:i'),
                'Contacto: '.$s->nombre_contacto.' · '.($s->email ?: 'sin correo').($s->whatsapp ? ' · WhatsApp '.$s->whatsapp : ''),
                $p->cliente_nombre || $p->cliente_email ? 'Datos en Wompi: '.trim(($p->cliente_nombre ?? '').' '.($p->cliente_email ?? '')) : null,
                $p->coincidio_por_correo ? null : 'OJO: no coincidió por correo con el formulario; confirma que es la misma persona.',
                $s->email ? 'Ya se le envió la bienvenida con los pasos a seguir. Preparar su instancia.' : 'No hay correo del cliente: contactarlo por WhatsApp.',
                'Transacción Wompi '.$p->id_transaccion.' (detectado por '.($p->origen === 'webhook' ? 'webhook' : 'revisión').').',
            ])),
            'url' => url('/cuentas/'.$s->id),
        ]], array_values(array_unique([...config('clinea.avisos_a'), ...config('clinea.avisos_pago_a')])));
    }

    /** Un doctor pidió la demo desde la landing: es un lead. */
    public function demo(SolicitudDemo $d): void
    {
        $asunto = $d->estado === 'enviada'
            ? 'Clinea: nueva demo — '.$d->nombre.' ('.$d->especialidad.')'
            : 'Clinea: no se pudo entregar una demo — '.$d->nombre;

        $this->mandar($asunto, [[
            'titulo' => $d->etiquetaEstado().': '.$d->nombre,
            'lineas' => array_values(array_filter([
                $d->especialidad.' · '.$d->email.($d->whatsapp ? ' · WhatsApp '.$d->whatsapp : '').($d->pais ? ' · '.$d->pais : ''),
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

    private function mandar(string $asunto, array $bloques, ?array $a = null): void
    {
        try {
            Mail::to($a ?? config('clinea.avisos_a'))->send(new Aviso($asunto, $bloques));
        } catch (Throwable $e) {
            // Que un correo caído no tumbe la contratación ni la revisión.
            Log::error('No se pudo enviar el aviso: '.$asunto, ['error' => $e->getMessage()]);
        }
    }
}
