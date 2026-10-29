<?php

namespace Database\Factories;

use App\Models\Formato;
use App\Models\Pelicula;
use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Funcion>
 */
class FuncionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pelicula_id' => Pelicula::factory(),
            'sala_id' => Sala::factory(),
            'formato_id' => Formato::factory(),
            'fecha_hora' => now()->addDays(fake()->numberBetween(1, 10))->setTime(fake()->randomElement([15, 18, 21]), 0),
            'idioma' => fake()->randomElement(['DOB', 'SUB']),
            'precio_base' => 3500,
            'cancelada' => false,
        ];
    }
}
