<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Combos de pochoclos, bebidas y golosinas que se agregan a la compra de entradas.
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('categoria', 20)->default('combo'); // combo | pochoclo | bebida | golosina
            $table->string('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->string('imagen_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('reserva_combos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reserva_id');
            $table->unsignedBigInteger('combo_id');
            $table->string('nombre', 100); // copia del nombre y precio al momento de la compra
            $table->decimal('precio_unitario', 10, 2);
            $table->unsignedTinyInteger('cantidad');
            $table->timestamps();
        });

        Schema::table('reservas', function (Blueprint $table) {
            $table->decimal('combos_total', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('combos_total');
        });
        Schema::dropIfExists('reserva_combos');
        Schema::dropIfExists('combos');
    }
};
