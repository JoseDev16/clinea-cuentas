<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lo que el cliente escribe en «Alias» en Wompi (le pedimos el nombre de su clínica).
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->string('wompi_alias')->nullable()->after('wompi_nombre_suscriptor');
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropColumn('wompi_alias');
        });
    }
};
