<?php

namespace App\Services;

use App\Models\Reserva;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Avisa a n8n (N8N_COMPRA_WEBHOOK) de una compra pagada para que le mande al comprador el
// correo de confirmacion con el comprobante en PDF y el QR. Si falla, la compra igual queda
// confirmada y el comando comprobantes:reenviar lo vuelve a intentar.
class CompraNotifier
{
    public function __construct(private ComprobanteService $comprobantes) {}

    public function enviar(Reserva $reserva): bool
    {
        $webhook = config('services.n8n.compra_webhook');

        if (! $webhook) {
            Log::warning('N8N_COMPRA_WEBHOOK no esta configurado: no se envio el comprobante de la reserva '.$reserva->id);

            return false;
        }

        $datos = $this->comprobantes->datos($reserva);

        try {
            // n8n valida este header con una credencial "Header Auth" (N8N_WEBHOOK_TOKEN).
            Http::timeout(8)
                ->withHeaders(['X-Cine-Token' => (string) config('services.n8n.token')])
                ->post($webhook, ['evento' => 'compra_confirmada', ...$datos, 'mensaje' => $this->mensaje($datos)])
                ->throw();

            $reserva->update(['comprobante_enviado_en' => now()]);

            return true;
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el comprobante de la reserva '.$reserva->id.': '.$e->getMessage());

            return false;
        }
    }

    // Resumen en texto plano (para el cuerpo alternativo del correo o un mensaje de Telegram).
    private function mensaje(array $d): string
    {
        $pesos = fn ($monto) => '$'.number_format((float) $monto, 0, ',', '.');
        $combos = collect($d['combos'])->map(fn ($c) => "{$c['cantidad']}x {$c['nombre']}")->implode(', ');

        return "Hola {$d['comprador']['nombre']}! Tu compra en Cine Concordia está confirmada.\n"
            ."Película: {$d['pelicula']}\n"
            ."Función: {$d['funcion']['fecha']} {$d['funcion']['hora']} · {$d['funcion']['sala']} · {$d['funcion']['formato']}\n"
            .'Butacas: '.implode(', ', $d['butacas'])."\n"
            .($combos ? "Combos: {$combos}\n" : '')
            .($d['descuento_aplicado'] ? "Descuento aplicado: {$d['descuento_aplicado']['codigo']} (-".$pesos($d['descuento_aplicado']['monto']).")\n" : '')
            .'Total: '.$pesos($d['importes']['total'])."\n"
            ."Reserva Nº {$d['reserva']['id']}\n"
            .'Presentá el código QR en la entrada del cine. Te adjuntamos el comprobante en PDF.'
            .($d['descuento'] ? "\n\nTu regalo: {$d['descuento']['porcentaje']}% de descuento en tu próxima compra con el código {$d['descuento']['codigo']} (válido hasta {$d['descuento']['vence']}). Canjealo en la web al pagar." : '');
    }
}
