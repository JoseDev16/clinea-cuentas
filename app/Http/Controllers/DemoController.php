<?php

namespace App\Http\Controllers;

use App\Mail\DemoLista;
use App\Models\SolicitudDemo;
use App\Services\Avisos;
use App\Services\InstanciaDemo;
use App\Support\FormularioPublico;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * «Prueba Clinea 24 horas» de la landing: guarda el lead, crea el acceso en la
 * instancia demo y le manda al doctor su usuario y contraseña por correo.
 * Las credenciales NO se muestran en pantalla: solo llegan al correo, así el
 * acceso es de quien de verdad es dueño de esa dirección.
 */
class DemoController
{
    public function store(Request $request, InstanciaDemo $instancia, Avisos $avisos)
    {
        if (! FormularioPublico::desdeLaLanding($request)) {
            abort(403);
        }

        // Campo trampa: invisible para personas, los bots lo llenan.
        if (filled($request->input('sitio_web'))) {
            return redirect()->away('https://clinea.app/');
        }

        $request->merge([
            'nombre' => FormularioPublico::limpiar($request->input('nombre')),
            'email' => mb_strtolower(FormularioPublico::limpiar($request->input('email'))),
        ]);

        $v = Validator::make($request->all(), [
            'nombre' => FormularioPublico::reglasNombre(),
            'especialidad' => ['required', Rule::in(config('clinea.especialidades'))],
            'email' => ['required', 'email:rfc', 'max:160'],
        ], [
            'nombre.regex' => 'Tu nombre solo puede llevar letras, números y signos comunes (. , - \' & ( ) #).',
            'nombre.not_regex' => 'Escribe solo tu nombre, sin enlaces ni direcciones web.',
            'especialidad.in' => 'Elige tu especialidad de la lista.',
        ], [
            'nombre' => 'tu nombre',
            'especialidad' => 'tu especialidad',
            'email' => 'tu correo',
        ]);
        if ($v->fails()) {
            return $this->resultado('error', 422, errores: $v->errors()->all());
        }
        $datos = $v->validated();

        // Cada solicitud genera otra contraseña: con un tope por correo nadie
        // puede llenarle la bandeja a otro ni cambiarle la clave a cada rato.
        $recientes = SolicitudDemo::query()
            ->where('email', $datos['email'])
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($recientes >= config('clinea.demo.maximo_por_correo')) {
            return $this->resultado('limite', 429, email: $datos['email']);
        }

        $d = SolicitudDemo::create([
            'nombre' => $datos['nombre'],
            'especialidad' => $datos['especialidad'],
            'email' => $datos['email'],
            'pais' => $this->pais($request),
            'ip' => $request->ip(),
        ]);

        try {
            $acceso = $instancia->crearAcceso($d->nombre, $d->email, $d->especialidad, $d->pais);
        } catch (Throwable $e) {
            $existente = $e instanceof RuntimeException && $e->getMessage() === InstanciaDemo::CUENTA_EXISTENTE;
            $d->update([
                'estado' => $existente ? 'cuenta_existente' : 'error',
                'detalle' => $existente ? 'Ese correo ya es de una cuenta real en la demo.' : 'No se pudo crear el acceso: '.mb_substr($e->getMessage(), 0, 400),
            ]);
            if (! $existente) {
                Log::error("No se pudo crear la demo de la solicitud {$d->id}", ['error' => $e->getMessage()]);
            }
            $avisos->demo($d);

            return $this->resultado($existente ? 'cuenta_existente' : 'fallo', $existente ? 409 : 502, email: $d->email);
        }

        try {
            Mail::to($d->email, $d->nombre)->send(new DemoLista($d, $acceso));
        } catch (Throwable $e) {
            Log::error("No se pudo enviar el correo de la demo {$d->id}", ['error' => $e->getMessage()]);
            $d->update(['estado' => 'error', 'detalle' => 'Se creó el acceso pero no se pudo enviar el correo.']);
            $avisos->demo($d);

            return $this->resultado('fallo', 502, email: $d->email);
        }

        $d->update(['estado' => 'enviada', 'demo_expira_at' => Carbon::parse($acceso['expira_en'])]);
        $avisos->demo($d);

        return $this->resultado('enviada', 200, email: $d->email);
    }

    private function resultado(string $tipo, int $status, ?string $email = null, array $errores = [])
    {
        return response()->view('demo.resultado', compact('tipo', 'email', 'errores'), $status);
    }

    /** Solo para saber de dónde vienen los leads (GeoIP de nginx). */
    private function pais(Request $request): ?string
    {
        $p = strtoupper((string) $request->server('GEOIP_COUNTRY'));

        return preg_match('/^[A-Z]{2}$/', $p) ? $p : null;
    }
}
