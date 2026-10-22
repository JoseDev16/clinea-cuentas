<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En qué demo quedó el acceso: «sv» (demo.clinea.app) o «hn»
        // (demohn.clinea.app). Las anteriores son todas de la de El Salvador.
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->string('instancia', 5)->default('sv')->after('pais');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->dropColumn('instancia');
        });
    }
};
