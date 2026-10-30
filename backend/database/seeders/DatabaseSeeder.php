<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Datos de ejemplo del cine. El admin lo crea AdminSeeder con ADMIN_EMAIL / ADMIN_PASSWORD.
        $this->call([
            GeneroSeeder::class,
            FormatoSeeder::class,
            SalaSeeder::class,
            AsientoSeeder::class,
            PeliculaSeeder::class,
            FuncionSeeder::class,
        ]);
    }
}
