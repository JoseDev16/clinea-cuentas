<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Services\RevisorSuscripciones;
use App\Services\Wompi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Panel interno: quién contrató, quién paga y quién se atrasó. */
class CuentasController
{
    public function index(Request $request)
    {
        $estado = $request->query('estado');
        $buscar = trim((string) $request->query('q'));

        $suscripciones = Suscripcion::query()
            ->when($estado && isset(Suscripcion::ESTADOS[$estado]), fn ($q) => $q->where('estado', $estado))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('clinica', 'like', "%{$buscar}%")
                ->orWhere('nombre_contacto', 'like', "%{$buscar}%")
                ->orWhere('email', 'like', "%{$buscar}%")))
            ->orderByRaw("case estado when 'atrasada' then 0 when 'activa' then 1 when 'pendiente' then 2 else 3 end")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $conteos = Suscripcion::query()->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');
        $porActivar = Suscripcion::query()->whereIn('estado', ['activa', 'atrasada'])->whereNull('instancia_lista_at')->count();
        $mensual = Suscripcion::query()->whereIn('estado', ['activa', 'atrasada'])->sum('monto');

        return view('cuentas.index', compact('suscripciones', 'conteos', 'estado', 'buscar', 'porActivar', 'mensual'));
    }

    public function show(Suscripcion $suscripcion)
    {
        $suscripcion->load('pagos');

        return view('cuentas.show', ['s' => $suscripcion]);
    }

    public function revisar(Suscripcion $suscripcion, RevisorSuscripciones $revisor)
    {
        $eventos = $revisor->revisar($suscripcion);
        $error = collect($eventos)->firstWhere('tipo', 'error');

        return back()->with($error ? 'error' : 'ok', $error
            ? 'No se pudo consultar Wompi: '.$error['detalle']
            : 'Revisado en Wompi'.(count($eventos) ? ': '.collect($eventos)->pluck('tipo')->join(', ') : ', sin cambios.'));
    }

    public function instancia(Suscripcion $suscripcion)
    {
        $suscripcion->update(['instancia_lista_at' => $suscripcion->instancia_lista_at ? null : now()]);

        return back()->with('ok', $suscripcion->instancia_lista_at ? 'Marcada con la clínica activada.' : 'Se quitó la marca de clínica activada.');
    }

    public function notas(Request $request, Suscripcion $suscripcion)
    {
        $suscripcion->update($request->validate(['notas' => ['nullable', 'string', 'max:5000']]));

        return back()->with('ok', 'Notas guardadas.');
    }

    /**
     * Desactiva el enlace en Wompi para que no se le cobre más.
     */
    public function cancelar(Suscripcion $suscripcion, Wompi $wompi)
    {
        try {
            if ($suscripcion->wompi_enlace_id) {
                $wompi->desactivarEnlace($suscripcion->wompi_enlace_id);
            }
        } catch (Throwable $e) {
            Log::error("No se pudo desactivar el enlace de la suscripción {$suscripcion->id}", ['error' => $e->getMessage()]);

            return back()->with('error', 'Wompi no desactivó el enlace; no se canceló. '.$e->getMessage());
        }
        $suscripcion->update(['estado' => 'cancelada']);

        return back()->with('ok', 'Suscripción cancelada y enlace desactivado en Wompi.');
    }
}
