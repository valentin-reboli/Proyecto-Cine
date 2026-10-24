<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las butacas se bloquean apenas el cliente las elige, antes de que cargue
     * sus datos. Como todavia no hay correo para identificarlo, el bloqueo se
     * maneja con un token secreto que solo conoce el navegador que lo pidio.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('token_bloqueo', 64)->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('token_bloqueo');
        });
    }
};
