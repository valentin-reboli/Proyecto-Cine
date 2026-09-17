<?php

namespace App\Console\Commands;

use App\Models\Reserva;
use App\Models\ReservaAsiento;
use Illuminate\Console\Command;

class LiberarReservasVencidas extends Command
{
    protected $signature = 'reservas:liberar-vencidas';

    protected $description = 'Libera las butacas temporales cuya reserva vencio sin pagarse';

    public function handle(): int
    {
        $vencidas = ReservaAsiento::where('estado_bloqueo', 'temporal')
            ->where('expira_en', '<', now())
            ->get();

        if ($vencidas->isEmpty()) {
            $this->info('No hay butacas vencidas para liberar.');

            return self::SUCCESS;
        }

        $reservaIds = $vencidas->pluck('reserva_id')->unique();

        ReservaAsiento::whereIn('id', $vencidas->pluck('id'))
            ->update(['estado_bloqueo' => 'liberado']);

        Reserva::whereIn('id', $reservaIds)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'expirada']);

        $this->info("Se liberaron {$vencidas->count()} butaca(s) de {$reservaIds->count()} reserva(s) vencida(s).");

        return self::SUCCESS;
    }
}
