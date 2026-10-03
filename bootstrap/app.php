<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El formulario viene de la landing estática, que no tiene token CSRF;
        // se protege con chequeo de Origin, campo trampa y límite por IP.
        $middleware->validateCsrfTokens(except: ['contratar']);
        $middleware->redirectUsersTo(fn () => route('cuentas.index'));
        $middleware->web(append: [\App\Http\Middleware\CabecerasSeguridad::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
