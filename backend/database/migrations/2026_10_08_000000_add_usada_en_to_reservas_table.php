<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Momento en que la entrada se valido en la puerta. NULL mientras no se
     * uso, asi una misma entrada no puede entrar dos veces.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->timestamp('usada_en')->nullable()->after('codigo_qr');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('usada_en');
        });
    }
};
