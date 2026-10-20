<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// Asegura un admin a partir de ADMIN_EMAIL y ADMIN_PASSWORD (variables del servidor).
// Si el usuario ya existe le pone esa contraseña, asi sirve tambien para recuperar el acceso.
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        $usuario = User::firstOrNew(['email' => $email]);
        $usuario->name ??= 'Admin';
        $usuario->password = $password;
        $usuario->save();

        $this->command?->info("Admin listo: {$email}");
    }
}
