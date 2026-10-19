<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Si el doctor de verdad entró a su demo, y cuándo (lo trae cuentas:actividad-demos). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->timestamp('primer_ingreso_at')->nullable();
            $table->timestamp('ultimo_ingreso_at')->nullable();
            $table->unsignedInteger('ingresos')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->dropColumn(['primer_ingreso_at', 'ultimo_ingreso_at', 'ingresos']);
        });
    }
};
