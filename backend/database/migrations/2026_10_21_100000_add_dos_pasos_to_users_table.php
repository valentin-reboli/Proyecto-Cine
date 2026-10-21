<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Secreto de Google Authenticator (encriptado). Sin activado_en, todavia no se confirmo.
            $table->text('dos_pasos_secreto')->nullable();
            $table->timestamp('dos_pasos_activado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dos_pasos_secreto', 'dos_pasos_activado_en']);
        });
    }
};
