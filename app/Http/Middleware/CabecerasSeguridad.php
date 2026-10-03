<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP para las páginas de Laravel (panel, login, errores de contratar): solo
 * se ejecuta el script propio que lleva el nonce, así un texto inyectado en
 * el nombre de una clínica no puede correr JavaScript en el panel.
 * Las cabeceras generales (HSTS, nosniff, marcos) las pone nginx para todo
 * clinea.app.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'nonce-".Vite::cspNonce()."'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https://clinea.app",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        // El panel muestra datos de clientes: que el navegador no lo guarde.
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
