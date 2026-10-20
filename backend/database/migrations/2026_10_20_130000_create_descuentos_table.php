<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Codigo de descuento para la proxima compra; solo lo puede usar el correo al que se regalo.
        Schema::create('descuentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('correo');
            $table->unsignedTinyInteger('porcentaje');
            $table->timestamp('vence_en');
            $table->timestamp('usado_en')->nullable();
            $table->unsignedBigInteger('reserva_origen_id')->nullable();
            $table->timestamps();
        });

        Schema::table('reservas', function (Blueprint $table) {
            $table->unsignedBigInteger('descuento_id')->nullable();
            $table->decimal('descuento_monto', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['descuento_id', 'descuento_monto']);
        });
        Schema::dropIfExists('descuentos');
    }
};
