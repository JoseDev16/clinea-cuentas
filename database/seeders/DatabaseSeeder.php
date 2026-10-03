<?php

namespace Database\Seeders;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Solo para desarrollo local: un usuario del panel y suscripciones de ejemplo.
 * En producción el usuario se crea con `php artisan cuentas:usuario`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('El seeder de ejemplo no corre en producción.');

            return;
        }

        User::updateOrCreate(['email' => 'admin@clinea.test'], ['name' => 'Admin local', 'password' => 'clinea-dev-1234']);

        $base = ['whatsapp' => '+503 7000 0000', 'wompi_url' => 'https://lk.wompi.sv/demo'];
        $ejemplos = [
            ['clinica' => 'Clínica San Rafael', 'nombre_contacto' => 'Dra. Ana López', 'email' => 'ana@ejemplo.com', 'pais' => 'SV', 'plan' => 'whatsapp', 'monto' => 14, 'dia_cobro' => 5, 'estado' => 'activa', 'pagos_realizados' => 3, 'instancia_lista_at' => now()->subMonths(2)],
            ['clinica' => 'Consultorio Pediátrico Sonrisas', 'nombre_contacto' => 'Dr. Luis Mejía', 'email' => 'luis@ejemplo.com', 'pais' => 'HN', 'plan' => 'expediente', 'monto' => 9.99, 'dia_cobro' => 12, 'estado' => 'activa', 'pagos_realizados' => 1, 'instancia_lista_at' => null],
            ['clinica' => 'Centro Médico La Paz', 'nombre_contacto' => 'Dra. Sofía Ramos', 'email' => 'sofia@ejemplo.com', 'pais' => 'SV', 'plan' => 'expediente', 'monto' => 9, 'dia_cobro' => 1, 'estado' => 'atrasada', 'pagos_realizados' => 2, 'instancia_lista_at' => now()->subMonth()],
            ['clinica' => 'Psicología Integral', 'nombre_contacto' => 'Lic. Marta Cruz', 'email' => 'marta@ejemplo.com', 'pais' => 'SV', 'plan' => 'whatsapp', 'monto' => 14, 'dia_cobro' => 20, 'estado' => 'pendiente', 'pagos_realizados' => 0, 'instancia_lista_at' => null],
        ];

        foreach ($ejemplos as $i => $e) {
            $s = Suscripcion::updateOrCreate(['email' => $e['email']], $base + $e + [
                'wompi_enlace_id' => 'demo-'.$i,
                'primer_pago_at' => $e['pagos_realizados'] ? now()->subMonths($e['pagos_realizados']) : null,
                'ultimo_pago_at' => $e['pagos_realizados'] ? now()->subDays(20) : null,
            ]);
            for ($n = 1; $n <= $e['pagos_realizados']; $n++) {
                Pago::updateOrCreate(['suscripcion_id' => $s->id, 'numero' => $n], ['monto' => $e['monto'], 'detectado_at' => now()->subMonths($e['pagos_realizados'] - $n)]);
            }
        }
    }
}
