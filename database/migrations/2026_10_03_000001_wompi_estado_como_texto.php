<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wompi devuelve el estado de la suscripción como texto («Activa»), no
        // como número: con integer, Postgres rechazaba el guardado y la
        // revisión nunca registraba nada.
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->string('wompi_estado', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->integer('wompi_estado')->nullable()->change();
        });
    }
};
