<?php

namespace Database\Factories;

use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Asiento>
 */
class AsientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sala_id' => Sala::factory(),
            'fila' => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'numero' => fake()->unique()->numberBetween(1, 9999),
            'activo' => true,
        ];
    }
}
