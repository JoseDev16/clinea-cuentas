<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Protecciones comunes de los formularios públicos de la landing (Contratar y
 * Prueba gratis): lo que escribe la gente termina en Wompi, en correos, en
 * PDF y en el panel, así que se limpia y se valida igual en todos.
 */
class FormularioPublico
{
    // Letras (con tildes), números, espacios y los signos de un nombre o razón social.
    public const SOLO_NOMBRE = "/^[\\pL\\pM\\pN .,'&()#\\-]+$/u";

    // Dominios: nadie llama a su clínica «algo.com»; así no se cuela un enlace
    // en el texto que Wompi muestra al pagar ni en el correo de bienvenida.
    public const PARECE_ENLACE = '/(www\\.|\\.(com|net|org|info|biz|xyz|io|app|dev|co|me|ly|link|site|online|top|shop|click|live|sv|hn|gt|mx|es)\\b)/iu';

    /**
     * Solo se aceptan formularios enviados desde la landing. Los navegadores
     * siempre mandan Origin (o al menos Referer) en un POST; si no viene
     * ninguno, es un script.
     */
    public static function desdeLaLanding(Request $request): bool
    {
        return app()->isLocal() || in_array(self::origen($request), config('clinea.origenes'), true);
    }

    /** Reglas de un nombre de persona o de clínica. */
    public static function reglasNombre(): array
    {
        return ['required', 'string', 'min:3', 'max:120', 'regex:'.self::SOLO_NOMBRE, 'not_regex:'.self::PARECE_ENLACE];
    }

    /**
     * Quita caracteres de control e invisibles, signos que solo sirven para
     * inyectar (< > " ` { } [ ] | \\ ; : @ / = $ % ~ ^ *) y espacios de más.
     * El correo se valida aparte (su @ y sus puntos se conservan).
     */
    public static function limpiar(mixed $valor): string
    {
        if (! is_string($valor)) {
            return '';
        }
        $valor = preg_replace('/[\\p{C}\\x{2028}\\x{2029}]+/u', ' ', $valor) ?? '';
        if (! str_contains($valor, '@')) {
            $valor = preg_replace('/[<>"`{}\\[\\]|\\\\;:\\/=$%~^*]+/u', ' ', $valor) ?? '';
        }

        return trim(preg_replace('/\\s+/u', ' ', $valor) ?? '');
    }

    public static function origen(Request $request): ?string
    {
        if ($origen = $request->headers->get('Origin')) {
            return $origen;
        }
        $referer = parse_url((string) $request->headers->get('Referer'));

        return isset($referer['scheme'], $referer['host']) ? $referer['scheme'].'://'.$referer['host'] : null;
    }
}
