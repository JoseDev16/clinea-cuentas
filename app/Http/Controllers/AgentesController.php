<?php

namespace App\Http\Controllers;

use App\Models\SolicitudDemo;
use App\Models\Suscripcion;
use App\Services\InstanciaDemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /cuentas/api/contacto?telefono=…: lo que se sabe de un teléfono en
 * Clinea (demos que pidió y lo que hizo en cada una, en orden, y si ya es
 * cliente). Lo consulta FSTUDIOS Agentes como herramienta del agente de
 * ventas, con su token (AGENTES_API_TOKEN).
 */
class AgentesController extends Controller
{
    public function contacto(Request $request, InstanciaDemo $instancia): JsonResponse
    {
        $token = (string) config('clinea.agentes_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        $digitos = preg_replace('/\D+/', '', (string) $request->validate(['telefono' => ['required', 'string', 'max:30']])['telefono']);
        abort_if(strlen($digitos) < 8, 422, 'Teléfono incompleto.');

        $solicitudes = SolicitudDemo::query()
            ->whereNotNull('whatsapp')
            ->latest('id')
            ->limit(2000)
            ->get()
            ->filter(fn (SolicitudDemo $s) => self::mismoTelefono($s->whatsapp, $digitos))
            ->sortBy('created_at')
            ->values();

        $demos = $solicitudes->map(function (SolicitudDemo $s) use ($instancia) {
            $demo = [
                'pedida_en' => $s->created_at?->toIso8601String(),
                'nombre' => $s->nombre,
                'especialidad' => $s->especialidad,
                'pais' => $s->pais,
                'demo' => $instancia->dominio($s->instancia ?? 'sv'),
                'estado' => $s->etiquetaEstado(),
            ];

            if ($s->estado === 'enviada') {
                try {
                    $demo['historial'] = $instancia->historial($s->email, $s->instancia ?? 'sv');
                } catch (Throwable) {
                    $demo['historial'] = null; // La demo no respondió: va lo que hay.
                }
            }

            return $demo;
        });

        $cliente = Suscripcion::query()->latest('id')->get()
            ->first(fn (Suscripcion $s) => self::mismoTelefono($s->whatsapp, $digitos));

        return response()->json([
            'telefono' => '+'.$digitos,
            'demos' => $demos->all(),
            'cliente' => $cliente ? [
                'clinica' => $cliente->clinica,
                'plan' => $cliente->plan,
                'estado' => $cliente->estado,
                'desde' => $cliente->created_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /** Mismo número aunque uno venga con prefijo y el otro no (+503 7884 5819 = 78845819). */
    private static function mismoTelefono(?string $guardado, string $digitos): bool
    {
        $g = preg_replace('/\D+/', '', (string) $guardado);

        return strlen($g) >= 8 && substr($g, -8) === substr($digitos, -8);
    }
}
