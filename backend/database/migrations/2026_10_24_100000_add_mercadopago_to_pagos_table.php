<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los pagos se cobran con Mercado Pago: se guarda el id del pago en Mercado Pago
     * (para no procesarlo dos veces y poder reembolsarlo) y el detalle del estado.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('mp_pago_id', 40)->nullable()->unique()->after('reserva_id');
            $table->string('detalle')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['mp_pago_id']);
            $table->dropColumn(['mp_pago_id', 'detalle']);
        });
    }
};
