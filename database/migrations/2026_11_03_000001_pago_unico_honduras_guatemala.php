<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Honduras y Guatemala pagan con un enlace fijo de Wompi (un solo pago
        // de un mes), no con un enlace recurrente propio por clínica.
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->string('tipo', 20)->default('recurrente')->after('plan');
        });

        // Cada transacción que Wompi reporta en los enlaces de pago único, venga
        // por webhook o por la revisión periódica. id_transaccion es único: si
        // llega por los dos caminos, se procesa una sola vez.
        Schema::create('pagos_wompi', function (Blueprint $table) {
            $table->id();
            $table->string('id_transaccion')->unique();
            $table->string('enlace_id');
            $table->string('pais', 2);
            $table->decimal('monto', 8, 2);
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_email')->nullable();
            $table->timestamp('pagado_at')->nullable();
            $table->string('origen', 20);           // webhook | revision
            $table->foreignId('suscripcion_id')->nullable()->constrained('suscripciones')->nullOnDelete();
            $table->boolean('coincidio_por_correo')->default(false);
            $table->timestamp('aviso_enviado_at')->nullable();
            $table->json('datos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_wompi');
        Schema::table('suscripciones', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
