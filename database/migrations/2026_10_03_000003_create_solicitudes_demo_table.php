<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada vez que alguien pide «Prueba Clinea 24 horas» en la landing:
        // es un lead, aunque después no contrate.
        Schema::create('solicitudes_demo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('especialidad', 80);
            $table->string('email')->index();
            $table->string('pais', 2)->nullable();
            // enviada → le llegó el correo con su acceso; cuenta_existente → el
            // correo ya es de una cuenta real en la demo; error → falló algo.
            $table->string('estado', 20)->default('pendiente')->index();
            $table->timestamp('demo_expira_at')->nullable();
            $table->text('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_demo');
    }
};
