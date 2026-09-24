<?php

namespace Database\Seeders;

use App\Models\Asiento;
use App\Models\Sala;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AsientoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 9 filas (A-I) x 10 asientos = 90 butacas por sala.
        $filas = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

        Sala::all()->each(function (Sala $sala) use ($filas) {
            foreach ($filas as $fila) {
                for ($numero = 1; $numero <= 10; $numero++) {
                    Asiento::create([
                        'sala_id' => $sala->id,
                        'fila' => $fila,
                        'numero' => $numero,
                    ]);
                }
            }
        });
    }
}
