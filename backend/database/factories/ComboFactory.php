<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Combo>
 */
class ComboFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Combo '.fake()->words(2, true),
            'categoria' => fake()->randomElement(['combo', 'pochoclo', 'bebida', 'golosina']),
            'descripcion' => fake()->sentence(),
            'precio' => fake()->numberBetween(10, 80) * 100,
            'activo' => true,
        ];
    }
}
