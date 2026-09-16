<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas');
            $table->foreignId('funcion_id')->constrained('funciones');
            $table->foreignId('asiento_id')->constrained('asientos');
            $table->decimal('precio_unitario', 8, 2);
            $table->string('estado_bloqueo')->default('temporal');
            $table->timestamp('expira_en')->nullable();
            $table->timestamps();

            $table->unique(['reserva_id', 'asiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_asientos');
    }
};
