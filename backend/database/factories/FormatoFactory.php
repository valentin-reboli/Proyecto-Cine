<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Formato>
 */
class FormatoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('?D-##'),
        ];
    }
}
