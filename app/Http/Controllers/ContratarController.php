<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Services\Avisos;
use App\Services\Wompi;
use App\Support\FormularioPublico;
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
        // Solo se acepta el formulario enviado desde la landing. Los navegadores
        // siempre mandan Origin (o al menos Referer) en un POST; si no viene
        // ninguno, es un script y se rechaza.
        if (! FormularioPublico::desdeLaLanding($request)) {
            abort(403);
        }

        // Campo trampa: invisible para personas, los bots lo llenan.
        if (filled($request->input('sitio_web'))) {
            return redirect()->away('https://clinea.app/');
        }

        // Lo que escribe el cliente termina en Wompi (nombre y descripción del
        // enlace), en el correo de bienvenida, en la carta PDF y en el panel:
        // se limpia antes de validar y solo se admite texto de un nombre.
        $request->merge([
            'nombre' => FormularioPublico::limpiar($request->input('nombre')),
            'clinica' => FormularioPublico::limpiar($request->input('clinica')),
            'email' => mb_strtolower(FormularioPublico::limpiar($request->input('email'))),
            'whatsapp' => FormularioPublico::limpiar($request->input('whatsapp')),
        ]);

        // La landing es HTML estático: no puede mostrar errores de vuelta, así
        // que se muestran aquí (la página ya valida lo mismo antes de enviar).
        $nombreValido = FormularioPublico::reglasNombre();
        $v = Validator::make($request->all(), [
            'plan' => ['required', Rule::in(['expediente', 'whatsapp'])],
            'pais' => ['nullable', 'string', 'size:2', 'alpha'],
            'nombre' => $nombreValido,
            'clinica' => $nombreValido,
            'email' => ['required', 'email:rfc', 'max:160'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[+\d\s\-()]{8,}$/'],
        ], [
            'whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
            'nombre.regex' => 'Tu nombre solo puede llevar letras, números y signos comunes (. , - \' & ( ) #).',
            'clinica.regex' => 'El nombre de la clínica solo puede llevar letras, números y signos comunes (. , - \' & ( ) #).',
            'nombre.not_regex' => 'Escribe solo tu nombre, sin enlaces ni direcciones web.',
            'clinica.not_regex' => 'Escribe solo el nombre de la clínica, sin enlaces ni direcciones web.',
        ], [
            'nombre' => 'tu nombre',
            'clinica' => 'el nombre de la clínica',
        ]);
        if ($v->fails()) {
            return response()->view('contratar.error', ['errores' => $v->errors()->all()], 422);
        }
        $datos = $v->validated();

        // Si vuelve a tocar «Contratar» (o recarga), se le manda al enlace que
        // ya tiene en vez de crear otro en Wompi.
        $previa = Suscripcion::query()
            ->where('email', $datos['email'])
            ->where('plan', $datos['plan'])
            ->where('estado', 'pendiente')
            ->whereNotNull('wompi_url')
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->first();
        if ($previa && $this->esEnlaceDeWompi($previa->wompi_url)) {
            return redirect()->away($previa->wompi_url);
        }

        // Tope por correo: nadie necesita más de unos pocos enlaces en un día.
        if (Suscripcion::query()->where('email', $datos['email'])->where('created_at', '>=', now()->subDay())->count() >= 3) {
            return response()->view('contratar.error', ['errores' => [
                'Ya recibimos varias solicitudes con este correo hoy. Escríbenos por WhatsApp y te ayudamos a terminar.',
            ]], 429);
        }

        $pais = $this->pais($request);
        $plan = config("clinea.planes.{$pais}.{$datos['plan']}");

        $s = Suscripcion::create([
            'pais' => $pais,
            'plan' => $datos['plan'],
            'monto' => $plan['monto'],
            'dia_cobro' => min(now()->day, config('clinea.dia_cobro_maximo')),
            'nombre_contacto' => $datos['nombre'],
            'clinica' => $datos['clinica'],
            'email' => $datos['email'],
            'whatsapp' => $datos['whatsapp'],
            'ip' => $request->ip(),
        ]);

        try {
            $enlace = $wompi->crearEnlaceRecurrente(
                nombre: "Clinea by fstudios · {$s->clinica} · {$plan['nombre']}",
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

        // Nunca se redirige a otro sitio que no sea Wompi.
        if (! $this->esEnlaceDeWompi($enlace['url'])) {
            Log::error("Wompi devolvió un enlace que no es de wompi.sv para la suscripción {$s->id}", ['url' => $enlace['url']]);

            return response()->view('contratar.error', ['s' => $s], 502);
        }

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
            "Suscripción mensual a Clinea — plan {$plan['nombre']} para {$s->clinica}: {$monto} al mes, cobrados el día {$s->dia_cobro} de cada mes.",
            'Importante: el comercio aparece con el nombre del representante legal de Clinea; es el mismo nombre que verás en tu estado de cuenta.',
            "Pasos: 1) En «Alias» escribe el nombre de tu clínica: {$s->clinica}. 2) Escribe los datos de tu tarjeta. 3) Acepta los términos y confirma. 4) Cuando te pregunte si deseas guardar la suscripción, elige «Sí» para que el cobro sea automático cada mes. 5) Listo: te escribimos por WhatsApp para activar tu clínica.",
            'Puedes cancelar cuando quieras escribiéndonos al WhatsApp +503 6678 1544.',
        ]);
    }


    private function esEnlaceDeWompi(?string $url): bool
    {
        $partes = parse_url((string) $url);

        return ($partes['scheme'] ?? null) === 'https'
            && (bool) preg_match('/(^|\\.)wompi\\.sv$/i', $partes['host'] ?? '');
    }

    /**
     * El país lo decide solo el servidor (GeoIP de nginx): precio de Honduras
     * o Guatemala si la IP es de allá; cualquier otro caso (El Salvador, otro
     * país, VPN, sin dato) paga el de El Salvador. El país que manda la página
     * se ignora, para que nadie elija el precio más barato.
     */
    private function pais(Request $request): string
    {
        $ip = strtoupper((string) $request->server('GEOIP_COUNTRY'));

        return in_array($ip, ['HN', 'GT'], true) ? $ip : 'SV';
    }
}
