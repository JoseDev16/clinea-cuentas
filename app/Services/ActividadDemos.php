<?php

namespace App\Services;

use App\Models\SolicitudDemo;
use Illuminate\Support\Carbon;

/**
 * Trae de la instancia demo si cada doctor entró a su prueba y lo guarda en
 * su solicitud. Así el panel dice a quién hay que buscar por WhatsApp: el que
 * pidió la demo y nunca entró es el que más seguimiento necesita.
 */
class ActividadDemos
{
    /** Solo se miran las solicitudes de las últimas semanas: las viejas ya no cambian. */
    public const DIAS = 30;

    public function __construct(private InstanciaDemo $instancia) {}

    /** @return int cuántas solicitudes cambiaron */
    public function sincronizar(int $timeout = 15): int
    {
        $cambios = 0;
        foreach (InstanciaDemo::INSTANCIAS as $instancia) {
            $cambios += $this->sincronizarInstancia($instancia, $timeout);
        }

        return $cambios;
    }

    /** Cada demo responde solo por los correos que se mandaron a ella. */
    private function sincronizarInstancia(string $instancia, int $timeout): int
    {
        $correos = SolicitudDemo::query()
            ->where('estado', 'enviada')
            ->where('instancia', $instancia)
            ->where('created_at', '>=', now()->subDays(self::DIAS))
            ->distinct()
            ->pluck('email')
            ->all();

        $cambios = 0;
        foreach (array_chunk($correos, 100) as $lote) {
            foreach ($this->instancia->actividad($lote, $timeout, $instancia) as $correo => $d) {
                $datos = [
                    'primer_ingreso_at' => isset($d['primer_ingreso_en']) ? Carbon::parse($d['primer_ingreso_en']) : null,
                    'ultimo_ingreso_at' => isset($d['ultimo_ingreso_en']) ? Carbon::parse($d['ultimo_ingreso_en']) : null,
                    'ingresos' => (int) ($d['ingresos'] ?? 0),
                ];
                // Si se le renovó la demo desde el EMR, el vencimiento de aquí se pone al día.
                if (! empty($d['expira_en'])) {
                    $datos['demo_expira_at'] = Carbon::parse($d['expira_en']);
                }

                foreach (SolicitudDemo::query()->where('email', $correo)->where('estado', 'enviada')->where('instancia', $instancia)->get() as $s) {
                    $s->fill($datos);
                    if ($s->isDirty()) {
                        $s->save();
                        $cambios++;
                    }
                }
            }
        }

        return $cambios;
    }
}
