<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('peliculas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('sinopsis')->nullable();
            $table->unsignedInteger('duracion_min');
            $table->string('clasificacion')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->string('trailer_url')->nullable();
            $table->boolean('en_cartelera')->default(true);
            $table->date('fecha_estreno')->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignId('genero_id')->constrained('generos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peliculas');
    }
};
