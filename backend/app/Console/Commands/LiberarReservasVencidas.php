<?php

namespace App\Console\Commands;

use App\Models\ReservaAsiento;
use Illuminate\Console\Command;

class LiberarReservasVencidas extends Command
{
    protected $signature = 'reservas:liberar-vencidas';

    protected $description = 'Libera las butacas temporales cuya reserva vencio sin pagarse';

    public function handle(): int
    {
        [$butacas, $reservas] = ReservaAsiento::liberarVencidas();

        if ($butacas === 0) {
            $this->info('No hay butacas vencidas para liberar.');

            return self::SUCCESS;
        }

        $this->info("Se liberaron {$butacas} butaca(s) de {$reservas} reserva(s) vencida(s).");

        return self::SUCCESS;
    }
}
