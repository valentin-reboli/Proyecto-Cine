<?php

namespace Database\Seeders;

use App\Models\Sala;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salas = ['Sala A', 'Sala B', 'Sala C'];

        foreach ($salas as $nombre) {
            Sala::create(['nombre' => $nombre]);
        }
    }
}
