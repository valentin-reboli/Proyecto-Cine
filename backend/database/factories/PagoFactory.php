<?php

namespace Database\Factories;

use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Pago>
 */
class PagoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reserva_id' => Reserva::factory(),
            'metodo' => 'tarjeta',
            'estado' => 'aprobado',
            'monto' => 3500,
            'pagado_en' => now(),
        ];
    }
}
