<?php

use App\Models\User;
use App\Services\Avisos;
use App\Services\RevisorSuscripciones;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cuentas:revisar {--sin-avisos : No manda el correo de resumen}', function (RevisorSuscripciones $revisor, Avisos $avisos) {
    $eventos = $revisor->revisar();
    foreach ($eventos as $e) {
        $this->line(str_pad($e['tipo'], 12).' '.$e['suscripcion']->clinica.(isset($e['detalle']) ? ' — '.$e['detalle'] : ''));
    }
    $this->info(count($eventos).' movimiento(s).');
    if (! $this->option('sin-avisos')) {
        $avisos->resumen($eventos);
    }
})->purpose('Consulta en Wompi los pagos de cada suscripción y avisa de primeros pagos y atrasos');

Artisan::command('cuentas:usuario {email} {nombre}', function (string $email, string $nombre) {
    $clave = $this->secret('Contraseña (mínimo 10 caracteres)');
    if (mb_strlen((string) $clave) < 10) {
        return $this->error('La contraseña debe tener al menos 10 caracteres.');
    }
    User::updateOrCreate(['email' => $email], ['name' => $nombre, 'password' => $clave]);
    $this->info("Usuario {$email} listo para entrar a /cuentas.");
})->purpose('Crea o actualiza un usuario del panel de cuentas');

// Cada hora: un primer pago se ve pronto para activar la clínica el mismo día.
Schedule::command('cuentas:revisar')->hourly()->withoutOverlapping();
