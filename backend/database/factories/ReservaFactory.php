<?php

namespace Database\Factories;

use App\Models\Funcion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Reserva>
 */
class ReservaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'funcion_id' => Funcion::factory(),
            'invitado_nombre' => fake()->name(),
            'invitado_correo' => fake()->safeEmail(),
            'invitado_telefono' => fake()->numerify('343#######'),
            'total' => 3500,
            'estado' => 'pendiente',
        ];
    }
}
