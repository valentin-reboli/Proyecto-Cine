<?php

namespace Database\Seeders;

use App\Models\Formato;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormatoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formatos = ['2D', '3D', 'ATMOS'];

        foreach ($formatos as $nombre) {
            Formato::create(['nombre' => $nombre]);
        }
    }
}
