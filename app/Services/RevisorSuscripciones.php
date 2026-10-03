<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wompi no avisa (webhook) de los cobros de un enlace recurrente, así que se
 * le pregunta: por cada clínica se lee la suscripción de su enlace y se
 * compara cuántos pagos lleva contra los que ya teníamos registrados.
 */
class RevisorSuscripciones
{
    public function __construct(private Wompi $wompi) {}

    /**
     * @return array<int, array{tipo: string, suscripcion: Suscripcion, detalle?: string}>
     */
    public function revisar(?Suscripcion $solo = null): array
    {
        $eventos = [];
        $query = Suscripcion::query()
            ->whereNotNull('wompi_enlace_id')
            ->where('estado', '!=', 'cancelada');
        if ($solo) {
            $query->whereKey($solo->id);
        }

        foreach ($query->get() as $s) {
            try {
                array_push($eventos, ...$this->revisarUna($s));
            } catch (Throwable $e) {
                Log::warning("No se pudo revisar la suscripción {$s->id} en Wompi", ['error' => $e->getMessage()]);
                $eventos[] = ['tipo' => 'error', 'suscripcion' => $s, 'detalle' => $e->getMessage()];
            }
        }

        return $eventos;
    }

    private function revisarUna(Suscripcion $s): array
    {
        $eventos = [];
        $subs = $this->wompi->suscripciones($s->wompi_enlace_id);

        // Normalmente el enlace es de una sola clínica y tiene una suscripción;
        // si alguien se suscribió dos veces, manda la que más pagos lleva.
        usort($subs, fn ($a, $b) => ($b['pagosRealizados'] ?? 0) <=> ($a['pagosRealizados'] ?? 0));
        $w = $subs[0] ?? null;

        if (count($subs) > 1) {
            $eventos[] = ['tipo' => 'varias', 'suscripcion' => $s, 'detalle' => count($subs).' suscripciones en el mismo enlace'];
        }

        DB::transaction(function () use ($s, $w, &$eventos) {
            $s->revisada_at = now();

            if ($w) {
                $s->wompi_suscripcion_id = $w['id'] ?? $s->wompi_suscripcion_id;
                $s->wompi_estado = $w['estado'] ?? null;
                $s->wompi_nombre_suscriptor = $w['nombreSuscriptor'] ?? $s->wompi_nombre_suscriptor;
                $s->wompi_alias = $w['alias'] ?? $s->wompi_alias;

                $pagos = (int) ($w['pagosRealizados'] ?? 0);
                if ($pagos > $s->pagos_realizados) {
                    for ($n = $s->pagos_realizados + 1; $n <= $pagos; $n++) {
                        Pago::firstOrCreate(
                            ['suscripcion_id' => $s->id, 'numero' => $n],
                            ['monto' => $w['monto'] ?? $s->monto, 'detectado_at' => now()],
                        );
                    }
                    $eventos[] = [
                        'tipo' => $s->pagos_realizados === 0 ? 'primer_pago' : 'pago',
                        'suscripcion' => $s,
                        'detalle' => "pago #{$pagos}",
                    ];
                    $s->pagos_realizados = $pagos;
                    $s->primer_pago_at ??= now();
                    $s->ultimo_pago_at = now();
                    $s->estado = 'activa';
                }
            }

            if ($s->estado === 'activa') {
                $vencido = $s->ultimoCobroVencido();
                if ($vencido && $s->ultimo_pago_at && $s->ultimo_pago_at->lt($vencido)) {
                    $s->estado = 'atrasada';
                    $eventos[] = ['tipo' => 'atraso', 'suscripcion' => $s, 'detalle' => 'tocaba cobrar el '.$vencido->format('d/m/Y')];
                }
            }

            $s->save();
        });

        return $eventos;
    }
}
