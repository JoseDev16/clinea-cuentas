<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contratar crea un enlace en Wompi y manda correos: se limita por IP y
        // con un tope total por hora, para que un script no llene la cuenta de
        // Wompi de enlaces ni la bandeja de avisos.
        RateLimiter::for('contratar', fn (Request $request) => [
            Limit::perMinute(5)->by('min:'.$request->ip())->response($this->demasiadas(...)),
            Limit::perDay(20)->by('dia:'.$request->ip())->response($this->demasiadas(...)),
            Limit::perHour(60)->by('todas')->response($this->demasiadas(...)),
        ]);

        // Login del panel: por IP y por correo (aunque cambie de IP).
        RateLimiter::for('entrar', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perHour(10)->by('correo:'.mb_strtolower((string) $request->input('email'))),
        ]);
    }

    private function demasiadas()
    {
        return response()->view('contratar.error', ['errores' => [
            'Recibimos demasiadas solicitudes. Intenta de nuevo en unos minutos o escríbenos por WhatsApp.',
        ]], 429);
    }
}
