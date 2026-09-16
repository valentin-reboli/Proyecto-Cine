<?php

namespace Database\Seeders;

use App\Models\Genero;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GeneroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generos = ['Accion', 'Comedia', 'Drama', 'Terror', 'Ciencia Ficcion', 'Animacion'];

        foreach ($generos as $nombre) {
            Genero::create(['nombre' => $nombre]);
        }
    }
}
