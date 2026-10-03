<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            // Cuándo apareció su suscripción en Wompi (tarjeta registrada).
            // Desde ahí corre el plazo para entregarle su instancia.
            $table->timestamp('suscrita_at')->nullable()->after('wompi_alias');
            // El correo de bienvenida con la carta en PDF se manda una sola vez.
            $table->timestamp('bienvenida_enviada_at')->nullable()->after('suscrita_at');
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropColumn(['suscrita_at', 'bienvenida_enviada_at']);
        });
    }
};
