<?php

namespace Database\Seeders;

use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Pelicula;
use App\Models\Sala;
use Illuminate\Database\Seeder;

class FuncionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salas = Sala::all();
        $formato2D = Formato::where('nombre', '2D')->firstOrFail();
        $formato3D = Formato::where('nombre', '3D')->firstOrFail();
        $horarios = ['15:00', '18:00', '21:00'];

        Pelicula::where('en_cartelera', true)->get()->each(function (Pelicula $pelicula, int $i) use ($salas, $formato2D, $formato3D, $horarios) {
            $sala = $salas[$i % $salas->count()];

            foreach ($horarios as $j => $hora) {
                [$h, $m] = explode(':', $hora);

                Funcion::create([
                    'pelicula_id' => $pelicula->id,
                    'sala_id' => $sala->id,
                    'formato_id' => $j === 1 ? $formato3D->id : $formato2D->id,
                    'fecha_hora' => now()->addDays($j)->setTime((int) $h, (int) $m, 0),
                    'idioma' => $j % 2 === 0 ? 'DOB' : 'SUB',
                    'precio_base' => 3500,
                ]);
            }
        });
    }
}
