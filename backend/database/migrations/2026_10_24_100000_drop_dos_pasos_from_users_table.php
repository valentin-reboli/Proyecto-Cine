<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Se saco la verificacion en dos pasos con Google Authenticator: el panel
    // se usa con email/contraseña o con la cuenta de Google.
    public function up(): void
    {
        if (Schema::hasColumn('users', 'dos_pasos_secreto')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['dos_pasos_secreto', 'dos_pasos_activado_en']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('dos_pasos_secreto')->nullable();
            $table->timestamp('dos_pasos_activado_en')->nullable();
        });
    }
};
