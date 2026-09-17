<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garantiza que una misma butaca no pueda quedar TEMPORAL/OCUPADA en
     * mas de una reserva activa para la misma funcion. SQLite y PostgreSQL
     * soportan indices unicos parciales (WHERE); MySQL no, asi que ahi se
     * usa una columna generada que vale NULL cuando la butaca esta
     * liberada (MySQL ignora los NULL en indices unicos).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE reserva_asientos
                ADD COLUMN asiento_bloqueado_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE WHEN estado_bloqueo <> 'liberado' THEN asiento_id END
                ) STORED
            ");

            DB::statement('
                CREATE UNIQUE INDEX uq_butaca_funcion_activa
                ON reserva_asientos (funcion_id, asiento_bloqueado_id)
            ');
        } else {
            DB::statement("
                CREATE UNIQUE INDEX uq_butaca_funcion_activa
                ON reserva_asientos (funcion_id, asiento_id)
                WHERE estado_bloqueo <> 'liberado'
            ");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::statement('DROP INDEX uq_butaca_funcion_activa'.($driver === 'mysql' ? ' ON reserva_asientos' : ''));

        if ($driver === 'mysql') {
            Schema::table('reserva_asientos', function (Blueprint $table) {
                $table->dropColumn('asiento_bloqueado_id');
            });
        }
    }
};
