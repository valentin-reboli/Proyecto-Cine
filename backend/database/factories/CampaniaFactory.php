<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Campania>
 */
class CampaniaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->sentence(3),
            'canal' => 'email',
            'asunto' => fake()->sentence(4),
            'mensaje' => fake()->paragraph(),
            'estado' => 'borrador',
        ];
    }
}
