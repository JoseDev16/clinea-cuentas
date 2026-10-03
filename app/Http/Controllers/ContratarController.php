<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Services\Avisos;
use App\Services\Wompi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Recibe el formulario de «Contratar» de la landing (clinea.app), crea un
 * enlace recurrente de Wompi solo para esa clínica y redirige a pagar.
 */
class ContratarController
{
    public function store(Request $request, Wompi $wompi, Avisos $avisos)
    {
        $origen = $request->headers->get('Origin');
        if ($origen && ! in_array($origen, config('clinea.origenes'), true) && ! app()->isLocal()) {
            abort(403);
        }

        // Campo trampa: invisible para personas, los bots lo llenan.
        if (filled($request->input('sitio_web'))) {
            return redirect()->away('https://clinea.app/');
        }

        // La landing es HTML estático: no puede mostrar errores de vuelta, así
        // que se muestran aquí (la página ya valida lo mismo antes de enviar).
        $v = Validator::make($request->all(), [
            'plan' => ['required', Rule::in(['expediente', 'whatsapp'])],
            'pais' => ['nullable', 'string', 'size:2'],
            'nombre' => ['required', 'string', 'max:120'],
            'clinica' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[+\d\s\-()]{8,}$/'],
        ], [
            'whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
        ]);
        if ($v->fails()) {
            return response()->view('contratar.error', ['errores' => $v->errors()->all()], 422);
        }
        $datos = $v->validated();

        $pais = $this->pais($request, $datos['pais'] ?? null);
        $plan = config("clinea.planes.{$pais}.{$datos['plan']}");

        $s = Suscripcion::create([
            'pais' => $pais,
            'plan' => $datos['plan'],
            'monto' => $plan['monto'],
            'dia_cobro' => min(now()->day, config('clinea.dia_cobro_maximo')),
            'nombre_contacto' => $datos['nombre'],
            'clinica' => $datos['clinica'],
            'email' => mb_strtolower($datos['email']),
            'whatsapp' => $datos['whatsapp'],
            'ip' => $request->ip(),
        ]);

        try {
            $enlace = $wompi->crearEnlaceRecurrente(
                nombre: "Clínea by fstudios · {$s->clinica} · {$plan['nombre']}",
                monto: $plan['monto'],
                diaDePago: $s->dia_cobro,
                descripcion: $this->descripcion($s, $plan),
            );
        } catch (Throwable $e) {
            Log::error("No se pudo crear el enlace de Wompi para la suscripción {$s->id}", ['error' => $e->getMessage()]);
            $s->update(['notas' => 'No se pudo crear el enlace en Wompi: '.mb_substr($e->getMessage(), 0, 500)]);
            $avisos->solicitud($s);

            return response()->view('contratar.error', ['s' => $s], 502);
        }

        $s->update(['wompi_enlace_id' => $enlace['id'], 'wompi_url' => $enlace['url']]);
        $avisos->solicitud($s);

        return redirect()->away($enlace['url']);
    }

    /**
     * Texto que Wompi muestra junto al formulario de pago. Explica por qué el
     * comercio sale con otro nombre (la cuenta de Wompi está a nombre del
     * representante legal) y guía los pasos, para que el cliente no se asuste
     * ni se pierda. El nombre en sí no se escribe: Wompi ya lo muestra.
     */
    private function descripcion(Suscripcion $s, array $plan): string
    {
        $monto = '$'.number_format($plan['monto'], 2);

        return implode("\n", [
            "Suscripción mensual a Clínea — plan {$plan['nombre']} para {$s->clinica}: {$monto} al mes, cobrados el día {$s->dia_cobro} de cada mes.",
            'Importante: el comercio aparece con el nombre del representante legal de Clínea; es el mismo nombre que verás en tu estado de cuenta.',
            "Pasos: 1) En «Alias» escribe el nombre de tu clínica: {$s->clinica}. 2) Escribe los datos de tu tarjeta. 3) Acepta los términos y confirma. 4) Cuando te pregunte si deseas guardar la suscripción, elige «Sí» para que el cobro sea automático cada mes. 5) Listo: te escribimos por WhatsApp para activar tu clínica.",
            'Puedes cancelar cuando quieras escribiéndonos al WhatsApp +503 6678 1544.',
        ]);
    }

    /**
     * El país lo decide el servidor (GeoIP de nginx) para que nadie elija el
     * precio de otro país; si la IP no es SV/HN se usa el que detectó la página.
     */
    private function pais(Request $request, ?string $dePagina): string
    {
        $paises = array_keys(config('clinea.paises'));
        foreach ([$request->server('GEOIP_COUNTRY'), $dePagina] as $p) {
            $p = strtoupper((string) $p);
            if (in_array($p, $paises, true)) {
                return $p;
            }
        }

        return 'SV';
    }
}
