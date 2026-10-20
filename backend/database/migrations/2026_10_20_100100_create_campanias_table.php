<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('canal', 20); // email | whatsapp | telegram
            $table->string('asunto')->nullable();
            $table->text('mensaje');
            $table->timestamp('programada_para')->nullable();
            $table->string('estado', 20)->default('borrador'); // borrador | programada | enviada | error
            $table->unsignedInteger('destinatarios')->default(0);
            $table->timestamp('enviada_en')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanias');
    }
};
