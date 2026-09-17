<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cliente no se loguea para comprar (solo el admin se loguea, para
     * gestionar el catalogo). Por eso user_id pasa a ser opcional y se
     * agregan los datos de contacto del invitado, que son los que
     * realmente identifican la compra.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('invitado_nombre')->nullable()->after('funcion_id');
            $table->string('invitado_correo')->nullable()->after('invitado_nombre');
            $table->string('invitado_telefono')->nullable()->after('invitado_correo');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['invitado_nombre', 'invitado_correo', 'invitado_telefono']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
