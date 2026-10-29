<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Sala>
 */
class SalaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Sala '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
