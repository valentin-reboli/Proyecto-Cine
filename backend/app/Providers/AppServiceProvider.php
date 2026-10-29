<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Por seguridad, el token del admin vence si pasa un tiempo sin usarse.
        // Cada request lo renueva (Sanctum actualiza last_used_at), asi que mientras
        // se usa el panel la sesion no se corta.
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $esValido) {
            if (! $esValido) {
                return false;
            }

            $ultimoUso = $token->last_used_at ?? $token->created_at;
            $limite = now()->subMinutes(config('cine.admin_inactividad_minutos'));

            if ($ultimoUso->lt($limite)) {
                $token->delete();

                return false;
            }

            return true;
        });
    }
}
