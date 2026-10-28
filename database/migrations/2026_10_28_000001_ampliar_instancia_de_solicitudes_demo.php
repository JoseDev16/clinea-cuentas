<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** «odonto» y «odontohn» no caben en 5 caracteres. */
    public function up(): void
    {
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->string('instancia', 20)->default('sv')->change();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->string('instancia', 5)->default('sv')->change();
        });
    }
};
