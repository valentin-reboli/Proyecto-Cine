<?php

namespace Database\Seeders;

use App\Models\Genero;
use App\Models\Pelicula;
use Illuminate\Database\Seeder;

class PeliculaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $peliculas = [
            [
                'titulo' => 'Duro de Matar',
                'sinopsis' => 'Un policia de Nueva York queda atrapado en un rascacielos de Los Angeles tomado por un grupo de mercenarios durante la fiesta de fin de ano de su esposa.',
                'duracion_min' => 132,
                'clasificacion' => '+16',
                'genero' => 'Accion',
                'en_cartelera' => true,
            ],
            [
                'titulo' => 'Con Faldas y a lo Loco',
                'sinopsis' => 'Dos musicos presencian una matanza y, para escapar de la mafia, se disfrazan de mujeres y se unen a una banda femenina rumbo a Florida.',
                'duracion_min' => 122,
                'clasificacion' => 'ATP',
                'genero' => 'Comedia',
                'en_cartelera' => true,
            ],
            [
                'titulo' => 'Casablanca',
                'sinopsis' => 'En el Marruecos ocupado durante la Segunda Guerra Mundial, un cinico dueno de un cafe debe elegir entre su gran amor y ayudarla a ella y a su esposo a escapar de los nazis.',
                'duracion_min' => 102,
                'clasificacion' => 'ATP',
                'genero' => 'Drama',
                'en_cartelera' => true,
            ],
            [
                'titulo' => 'El Exorcista',
                'sinopsis' => 'Una madre busca ayuda desesperada cuando su hija comienza a mostrar signos de una posesion demoniaca.',
                'duracion_min' => 122,
                'clasificacion' => '+16',
                'genero' => 'Terror',
                'en_cartelera' => true,
            ],
            [
                'titulo' => '2001: Odisea del Espacio',
                'sinopsis' => 'El descubrimiento de un misterioso monolito lleva a una tripulacion a una mision hacia Jupiter, donde la inteligencia artificial de a bordo comienza a actuar por su cuenta.',
                'duracion_min' => 149,
                'clasificacion' => '+13',
                'genero' => 'Ciencia Ficcion',
                'en_cartelera' => false,
                'fecha_estreno' => now()->addDays(20)->toDateString(),
            ],
            [
                'titulo' => 'Blancanieves y los Siete Enanitos',
                'sinopsis' => 'Una joven princesa huye de su malvada madrastra y encuentra refugio en el bosque junto a siete entranables enanitos.',
                'duracion_min' => 83,
                'clasificacion' => 'ATP',
                'genero' => 'Animacion',
                'en_cartelera' => false,
                'fecha_estreno' => now()->addDays(35)->toDateString(),
            ],
        ];

        foreach ($peliculas as $data) {
            $genero = Genero::where('nombre', $data['genero'])->firstOrFail();

            Pelicula::create([
                'titulo' => $data['titulo'],
                'sinopsis' => $data['sinopsis'],
                'duracion_min' => $data['duracion_min'],
                'clasificacion' => $data['clasificacion'],
                'en_cartelera' => $data['en_cartelera'],
                'fecha_estreno' => $data['fecha_estreno'] ?? null,
                'activa' => true,
                'genero_id' => $genero->id,
            ]);
        }
    }
}
