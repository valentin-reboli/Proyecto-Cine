<?php

namespace Database\Factories;

use App\Models\Combo;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ReservaCombo>
 */
class ReservaComboFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reserva_id' => Reserva::factory(),
            'combo_id' => Combo::factory(),
            'nombre' => 'Combo '.fake()->word(),
            'precio_unitario' => 4500,
            'cantidad' => 1,
        ];
    }
}
