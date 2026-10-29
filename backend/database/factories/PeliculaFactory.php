<?php

namespace Database\Factories;

use App\Models\Genero;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Pelicula>
 */
class PeliculaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo' => fake()->sentence(3),
            'sinopsis' => fake()->paragraph(),
            'duracion_min' => fake()->numberBetween(80, 180),
            'clasificacion' => fake()->randomElement(['ATP', '+13', '+16', '+18']),
            'en_cartelera' => true,
            'fecha_estreno' => fake()->date(),
            'activa' => true,
            'genero_id' => Genero::factory(),
        ];
    }
}
