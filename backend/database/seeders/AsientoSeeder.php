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
        $filas = range(1, 9);

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
