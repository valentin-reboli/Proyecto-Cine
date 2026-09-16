<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelicula_id')->constrained('peliculas');
            $table->foreignId('sala_id')->constrained('salas');
            $table->foreignId('formato_id')->constrained('formatos');
            $table->timestamp('fecha_hora');
            $table->string('idioma');
            $table->decimal('precio_base', 8, 2);
            $table->boolean('cancelada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funciones');
    }
};
