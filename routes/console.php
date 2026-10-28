<?php

use App\Services\InstanciaDemo;
use App\Services\ReenvioDemoDental;
use App\Models\User;
use App\Services\ActividadDemos;
use App\Services\Avisos;
use App\Services\RevisorSuscripciones;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cuentas:revisar
    {--recientes : Solo las que pidieron su enlace hace poco y no se han suscrito (la revisión de cada minuto)}
    {--sin-avisos : No manda el correo de resumen}', function (RevisorSuscripciones $revisor, Avisos $avisos) {
    $eventos = $revisor->revisar(recientes: (bool) $this->option('recientes'));
    foreach ($eventos as $e) {
        $this->line(str_pad($e['tipo'], 12).' '.$e['suscripcion']->clinica.(isset($e['detalle']) ? ' — '.$e['detalle'] : ''));
    }
    $this->info(count($eventos).' movimiento(s).');
    if (! $this->option('sin-avisos')) {
        // La revisión de cada minuto no avisa de errores de Wompi: si Wompi se
        // cae llegaría un correo por minuto. Esos los avisa la de cada hora.
        $avisos->resumen($this->option('recientes')
            ? array_values(array_filter($eventos, fn ($e) => $e['tipo'] !== 'error'))
            : $eventos);
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

Artisan::command('cuentas:actividad-demos', function (ActividadDemos $actividad) {
    $this->info($actividad->sincronizar().' solicitud(es) de demo actualizada(s).');
})->purpose('Trae de las demos (demo, demohn, odonto y odontohn) si cada doctor entró a su demo y cuándo');

// Cada minuto, las recién pedidas: en cuanto alguien se suscribe en Wompi le
// llega la bienvenida, antes de que piense que pagó a un desconocido.
Schedule::command('cuentas:revisar --recientes')->everyMinute()->withoutOverlapping();

// Cada hora, todas: pagos de cada mes y atrasos.
Schedule::command('cuentas:revisar')->hourly()->withoutOverlapping();

// Cada 10 minutos: quién entró a su demo (para el panel de demos).
Schedule::command('cuentas:actividad-demos')->everyTenMinutes()->withoutOverlapping();

Artisan::command('cuentas:demo-dental {ids?* : Solicitudes (por defecto, todas las de Odontología)} {--enviar : Crear los accesos y mandar los correos; sin esto solo muestra a quién}', function (ReenvioDemoDental $reenvio) {
    $candidatas = $reenvio->candidatas(array_map('intval', $this->argument('ids')));
    if ($candidatas->isEmpty()) {
        $this->info('No hay odontólogos pendientes de la demo dental.');

        return;
    }

    $this->table(['#', 'Nombre', 'Correo', 'WhatsApp', 'Irá a'], $candidatas->map(fn ($s) => [
        $s->id, $s->nombre, $s->email, $s->whatsapp,
        app(InstanciaDemo::class)->dominio(app(InstanciaDemo::class)->para($reenvio->paisDe($s), InstanciaDemo::ODONTOLOGIA)),
    ])->all());

    if (! $this->option('enviar')) {
        $this->warn('Simulación: no se creó ni se envió nada. Repite con --enviar.');

        return;
    }

    foreach ($candidatas as $s) {
        try {
            $nueva = $reenvio->enviar($s);
            $this->line("✓ {$s->email} → solicitud #{$nueva->id} en {$nueva->instancia}");
        } catch (\Throwable $e) {
            $this->error("✗ {$s->email}: ".$e->getMessage());
        }
    }
})->purpose('Manda la demo dental (con la guía en PDF) a los odontólogos que pidieron la demo médica');
