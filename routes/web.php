<?php

use App\Http\Controllers\ContratarController;
use App\Http\Controllers\CuentasController;
use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

// En producción nginx solo manda a Laravel /contratar y /cuentas; lo demás
// de clinea.app es la landing estática.
Route::get('/', fn () => redirect()->route('cuentas.index'));

Route::get('/contratar', fn () => redirect()->away('https://clinea.app/#planes'));
Route::post('/contratar', [ContratarController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contratar');

Route::prefix('cuentas')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/entrar', [SesionController::class, 'create'])->name('login');
        Route::post('/entrar', [SesionController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/salir', [SesionController::class, 'destroy'])->name('logout');
        Route::get('/', [CuentasController::class, 'index'])->name('cuentas.index');
        Route::get('/{suscripcion}', [CuentasController::class, 'show'])->name('cuentas.show');
        Route::post('/{suscripcion}/revisar', [CuentasController::class, 'revisar'])->name('cuentas.revisar');
        Route::post('/{suscripcion}/instancia', [CuentasController::class, 'instancia'])->name('cuentas.instancia');
        Route::post('/{suscripcion}/notas', [CuentasController::class, 'notas'])->name('cuentas.notas');
        Route::post('/{suscripcion}/cancelar', [CuentasController::class, 'cancelar'])->name('cuentas.cancelar');
    });
});
