<?php

namespace App\Console\Commands;

use App\Models\Reserva;
use App\Services\CompraNotifier;
use Illuminate\Console\Command;

// Si n8n no respondio al momento de la compra, el comprobante queda sin enviar.
// Este comando lo reintenta para las compras de las ultimas 24 horas cuya funcion todavia no paso.
class ReenviarComprobantes extends Command
{
    protected $signature = 'comprobantes:reenviar';

    protected $description = 'Reintenta enviar a n8n los comprobantes de compras pagadas que no se pudieron enviar';

    public function handle(CompraNotifier $notificador): int
    {
        if (! config('services.n8n.compra_webhook')) {
            $this->info('N8N_COMPRA_WEBHOOK no esta configurado.');

            return self::SUCCESS;
        }

        $reservas = Reserva::where('estado', 'pagada')
            ->whereNull('comprobante_enviado_en')
            ->whereHas('pagos', fn ($q) => $q->where('estado', 'aprobado')->where('pagado_en', '>=', now()->subDay()))
            ->whereHas('funcion', fn ($q) => $q->where('fecha_hora', '>', now()))
            ->get();

        $enviados = $reservas->filter(fn (Reserva $reserva) => $notificador->enviar($reserva))->count();

        $this->info("Se enviaron {$enviados} de {$reservas->count()} comprobante(s) pendiente(s).");

        return self::SUCCESS;
    }
}
