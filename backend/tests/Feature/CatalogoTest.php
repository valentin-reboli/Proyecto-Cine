<?php

namespace Tests\Feature;

use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_de_peliculas_incluye_funciones_con_formato_y_excluye_canceladas(): void
    {
        $genero = Genero::create(['nombre' => 'Accion']);
        $sala = Sala::create(['nombre' => 'Sala Test']);
        $formato = Formato::create(['nombre' => '3D']);

        $pelicula = Pelicula::create([
            'titulo' => 'Pelicula Test',
            'duracion_min' => 100,
            'en_cartelera' => true,
            'activa' => true,
            'genero_id' => $genero->id,
        ]);

        $datos = [
            'pelicula_id' => $pelicula->id,
            'sala_id' => $sala->id,
            'formato_id' => $formato->id,
            'idioma' => 'DOB',
            'precio_base' => 1000,
        ];

        $vigente = Funcion::create($datos + ['fecha_hora' => now()->addDay()]);
        Funcion::create($datos + ['fecha_hora' => now()->addDays(2), 'cancelada' => true]);

        $respuesta = $this->getJson('/api/peliculas')->assertOk();

        $funciones = $respuesta->json('0.funciones');

        $this->assertCount(1, $funciones);
        $this->assertSame($vigente->id, $funciones[0]['id']);
        $this->assertSame('3D', $funciones[0]['formato']['nombre']);
    }
}
