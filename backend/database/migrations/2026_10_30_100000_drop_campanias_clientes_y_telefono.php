<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se saca el modulo de campañas: ya no se guardan los contactos de los
     * compradores (clientes) ni su telefono en las reservas.
     */
    public function up(): void
    {
        Schema::dropIfExists('campanias');
        Schema::dropIfExists('clientes');

        if (Schema::hasColumn('reservas', 'invitado_telefono')) {
            Schema::table('reservas', function (Blueprint $table) {
                $table->dropColumn('invitado_telefono');
            });
        }
    }

    // Vuelve a crear la estructura (los datos borrados no se recuperan).
    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('invitado_telefono')->nullable()->after('invitado_correo');
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->unique();
            $table->string('telefono', 30)->nullable();
            $table->boolean('acepta_marketing')->default(false);
            $table->timestamps();
        });

        Schema::create('campanias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('canal', 20);
            $table->string('asunto')->nullable();
            $table->text('mensaje');
            $table->timestamp('programada_para')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->unsignedInteger('destinatarios')->default(0);
            $table->timestamp('enviada_en')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
};
