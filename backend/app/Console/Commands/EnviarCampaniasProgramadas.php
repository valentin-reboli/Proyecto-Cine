<?php

namespace App\Console\Commands;

use App\Models\Campania;
use App\Services\CampaniaSender;
use Illuminate\Console\Command;

class EnviarCampaniasProgramadas extends Command
{
    protected $signature = 'campanias:enviar-programadas';

    protected $description = 'Envia las campañas programadas cuya fecha ya llego';

    public function handle(CampaniaSender $sender): int
    {
        Campania::where('estado', 'programada')
            ->where('programada_para', '<=', now())
            ->get()
            ->each(function (Campania $campania) use ($sender) {
                $resultado = $sender->enviar($campania);
                $this->info("Campaña {$campania->id}: {$resultado->estado}");
            });

        return self::SUCCESS;
    }
}
