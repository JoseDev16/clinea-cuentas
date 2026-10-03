<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila por clínica que contrata. Cada una tiene su propio enlace
        // recurrente en Wompi: así el enlace identifica a la clínica.
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->string('pais', 2);
            $table->string('plan', 20);
            $table->decimal('monto', 8, 2);
            $table->unsignedTinyInteger('dia_cobro');

            $table->string('nombre_contacto');
            $table->string('clinica');
            $table->string('email');
            $table->string('whatsapp', 30);

            // pendiente → activa (primer pago) → atrasada / cancelada
            $table->string('estado', 20)->default('pendiente')->index();
            $table->string('wompi_enlace_id')->nullable()->unique();
            $table->string('wompi_url')->nullable();
            $table->string('wompi_suscripcion_id')->nullable();
            $table->integer('wompi_estado')->nullable();
            $table->string('wompi_nombre_suscriptor')->nullable();
            $table->unsignedInteger('pagos_realizados')->default(0);
            $table->timestamp('primer_pago_at')->nullable();
            $table->timestamp('ultimo_pago_at')->nullable();
            $table->timestamp('revisada_at')->nullable();

            // Cuándo se dejó lista su instancia de Clínea (lo marca el admin).
            $table->timestamp('instancia_lista_at')->nullable();
            $table->text('notas')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();
            $table->unsignedInteger('numero');   // 1 = primer pago, 2 = segundo mes...
            $table->decimal('monto', 8, 2);
            $table->timestamp('detectado_at');
            $table->timestamps();

            $table->unique(['suscripcion_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('suscripciones');
    }
};
