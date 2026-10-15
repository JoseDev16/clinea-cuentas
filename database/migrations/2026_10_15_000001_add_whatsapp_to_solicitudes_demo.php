<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Con solo el correo no había cómo darle seguimiento al lead por
        // WhatsApp. Nullable: las solicitudes anteriores no lo traen.
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_demo', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
    }
};
