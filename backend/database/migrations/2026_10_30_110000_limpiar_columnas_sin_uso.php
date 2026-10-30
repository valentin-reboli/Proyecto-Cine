<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limpieza de lo que la app no usa:
     * - roles y users.role_id: todos los usuarios del panel son admin, nunca se asigno un rol.
     * - reservas.user_id: se compra sin cuenta, siempre quedaba vacia.
     * - pagos.comprobante: nunca se guardo nada ahi.
     * - El usuario de prueba test@example.com (contraseña "password") que creaba el seeder.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('comprobante');
        });

        $prueba = DB::table('users')
            ->where('email', 'test@example.com')
            ->where('name', 'Test User')
            ->when(env('ADMIN_EMAIL'), fn ($q, $admin) => $q->where('email', '!=', $admin))
            ->pluck('id');

        if ($prueba->isNotEmpty()) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->whereIn('tokenable_id', $prueba)
                ->delete();
            DB::table('users')->whereIn('id', $prueba)->delete();
        }
    }

    // Vuelve a crear la estructura (los datos borrados no se recuperan).
    public function down(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->string('comprobante')->nullable();
        });
    }
};
