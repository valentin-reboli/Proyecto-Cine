<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Descuento>
 */
class DescuentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'CINE-'.strtoupper(fake()->unique()->bothify('??####')),
            'correo' => fake()->safeEmail(),
            'porcentaje' => 10,
            'vence_en' => now()->addMonth(),
        ];
    }
}
