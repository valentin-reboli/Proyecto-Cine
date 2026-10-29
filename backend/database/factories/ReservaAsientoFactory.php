<?php

namespace Database\Factories;

use App\Models\Asiento;
use App\Models\Funcion;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ReservaAsiento>
 */
class ReservaAsientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reserva_id' => Reserva::factory(),
            'funcion_id' => Funcion::factory(),
            'asiento_id' => Asiento::factory(),
            'precio_unitario' => 3500,
            'estado_bloqueo' => 'temporal',
            'expira_en' => now()->addMinutes(10),
        ];
    }
}
