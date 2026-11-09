<?php

namespace App\Http\Controllers;

use App\Mail\DemoLista;
use App\Models\SolicitudDemo;
use App\Models\Suscripcion;
use App\Services\Avisos;
use App\Services\InstanciaDemo;
use App\Support\FormularioPublico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
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
        $this->autorizar($request);

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

    /**
     * POST /cuentas/api/demo {nombre, especialidad, correo, telefono}: el agente
     * de ventas le crea la demo a quien se la pidió por WhatsApp. El teléfono lo
     * pone la plataforma (es el de la conversación), así el acceso es de quien
     * escribe desde ese número. Devuelve el usuario y la contraseña para que el
     * agente se los mande en el chat; además le llegan por correo.
     * Si ese teléfono ya tiene una demo vigente no crea otra: lo dice.
     */
    public function crearDemo(Request $request, InstanciaDemo $instancia, Avisos $avisos): JsonResponse
    {
        $this->autorizar($request);

        $request->merge([
            'nombre' => FormularioPublico::limpiar($request->input('nombre')),
            'correo' => mb_strtolower(FormularioPublico::limpiar($request->input('correo'))),
        ]);
        $datos = $request->validate([
            'nombre' => FormularioPublico::reglasNombre(),
            'especialidad' => ['required', 'string', 'max:80'],
            'correo' => ['required', 'email:rfc', 'max:160'],
            'telefono' => ['required', 'string', 'max:30', 'regex:/^[+\d\s\-()]{8,}$/'],
        ]);
        $digitos = preg_replace('/\D+/', '', $datos['telefono']);
        $especialidad = self::especialidad($datos['especialidad']);

        $vigente = SolicitudDemo::query()->where('estado', 'enviada')->where('demo_expira_at', '>', now())
            ->whereNotNull('whatsapp')->latest('id')->limit(2000)->get()
            ->first(fn (SolicitudDemo $s) => self::mismoTelefono($s->whatsapp, $digitos));
        if ($vigente) {
            return response()->json([
                'resultado' => 'ya_tiene_demo',
                'mensaje' => 'Este número ya tiene una demo vigente; no se creó otra. Sus datos de acceso están en su correo.',
                'correo' => $vigente->email,
                'demo' => 'https://'.$instancia->dominio($vigente->instancia ?? 'sv'),
                'vence' => $vigente->demo_expira_at?->toIso8601String(),
            ]);
        }

        $recientes = SolicitudDemo::query()->where('email', $datos['correo'])->where('created_at', '>=', now()->subDay())->count();
        if ($recientes >= config('clinea.demo.maximo_por_correo')) {
            return response()->json(['resultado' => 'limite', 'mensaje' => 'Ese correo ya pidió demasiadas demos hoy. Que lo intente mañana o lo atienda una persona.'], 429);
        }

        $pais = self::paisDelTelefono($digitos);
        $d = SolicitudDemo::create([
            'nombre' => $datos['nombre'],
            'especialidad' => $especialidad,
            'email' => $datos['correo'],
            'whatsapp' => '+'.$digitos,
            'pais' => $pais,
            'instancia' => $instancia->para($pais, $especialidad),
            'detalle' => 'Pedida por WhatsApp al agente de ventas.',
        ]);

        try {
            $acceso = $instancia->crearAcceso($d->nombre, $d->email, $d->especialidad, $pais, $d->instancia, $d->whatsapp);
        } catch (Throwable $e) {
            $existente = $e instanceof RuntimeException && $e->getMessage() === InstanciaDemo::CUENTA_EXISTENTE;
            $d->update([
                'estado' => $existente ? 'cuenta_existente' : 'error',
                'detalle' => $existente ? 'Ese correo ya es de una cuenta real en la demo.' : 'No se pudo crear el acceso: '.mb_substr($e->getMessage(), 0, 400),
            ]);
            if (! $existente) {
                Log::error("No se pudo crear la demo (agente) {$d->id}", ['error' => $e->getMessage()]);
            }
            $avisos->demo($d);

            return response()->json($existente
                ? ['resultado' => 'correo_registrado', 'mensaje' => 'Ese correo ya es de una cuenta en Clinea. Pídale otro correo o páselo con una persona.']
                : ['resultado' => 'fallo', 'mensaje' => 'No se pudo crear la demo ahora. Páselo con una persona.'], $existente ? 409 : 502);
        }

        $d->update(['estado' => 'enviada', 'demo_expira_at' => Carbon::parse($acceso['expira_en'])]);
        try {
            Mail::to($d->email, $d->nombre)->send(new DemoLista($d, $acceso));
        } catch (Throwable $e) {
            Log::warning("No se pudo enviar el correo de la demo (agente) {$d->id}", ['error' => $e->getMessage()]);
        }
        $avisos->demo($d);

        return response()->json([
            'resultado' => 'creada',
            'enlace' => $acceso['url'],
            'usuario' => $acceso['correo'],
            'contrasena' => $acceso['password'],
            'vence' => $acceso['expira_en'],
            'especialidad' => $especialidad,
        ], 201);
    }

    private function autorizar(Request $request): void
    {
        $token = (string) config('clinea.agentes_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);
    }

    /** La especialidad que dijo en el chat, llevada a la de la lista («ginecóloga» → Ginecología y obstetricia). */
    public static function especialidad(string $dicha): string
    {
        $lista = config('clinea.especialidades');
        $d = Str::of($dicha)->ascii()->lower()->squish()->value();
        foreach ($lista as $e) {
            if (Str::of($e)->ascii()->lower()->value() === $d) {
                return $e;
            }
        }
        $raices = [
            'general' => 'Medicina general', 'familiar' => 'Medicina familiar', 'interna' => 'Medicina interna', 'internista' => 'Medicina interna',
            'pediatr' => 'Pediatría', 'ginec' => 'Ginecología y obstetricia', 'obstet' => 'Ginecología y obstetricia', 'cardio' => 'Cardiología',
            'dermat' => 'Dermatología', 'ortop' => 'Ortopedia y traumatología', 'trauma' => 'Ortopedia y traumatología', 'neurol' => 'Neurología',
            'gastro' => 'Gastroenterología', 'endocrin' => 'Endocrinología', 'psicol' => 'Psicología', 'psiquiat' => 'Psiquiatría',
            'nutri' => 'Nutrición', 'odont' => 'Odontología', 'dentist' => 'Odontología', 'dental' => 'Odontología', 'fisio' => 'Fisioterapia',
        ];
        foreach ($raices as $raiz => $e) {
            if (str_contains($d, $raiz)) {
                return $e;
            }
        }

        return 'Otra especialidad';
    }

    private static function paisDelTelefono(string $digitos): ?string
    {
        foreach (['504' => 'HN', '502' => 'GT', '503' => 'SV'] as $prefijo => $pais) {
            if (str_starts_with($digitos, $prefijo) && strlen($digitos) === 11) {
                return $pais;
            }
        }

        return strlen($digitos) === 8 ? 'SV' : null;
    }

    /** Mismo número aunque uno venga con prefijo y el otro no (+503 7884 5819 = 78845819). */
    private static function mismoTelefono(?string $guardado, string $digitos): bool
    {
        $g = preg_replace('/\D+/', '', (string) $guardado);

        return strlen($g) >= 8 && substr($g, -8) === substr($digitos, -8);
    }
}
