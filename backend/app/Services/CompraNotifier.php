<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Reserva;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Avisa a n8n (N8N_COMPRA_WEBHOOK) para que le mande un correo al comprador:
// - compra_confirmada: comprobante en PDF y QR. Si falla, la compra igual queda confirmada
//   y el comando comprobantes:reenviar lo vuelve a intentar.
// - pago_pendiente, pago_rechazado y compra_devuelta: la compra no se completo (todavia).
class CompraNotifier
{
    public function __construct(private ComprobanteService $comprobantes) {}

    public function enviar(Reserva $reserva): bool
    {
        $datos = $this->comprobantes->datos($reserva);

        if (! $this->post(['evento' => 'compra_confirmada', ...$datos, 'mensaje' => $this->mensaje($datos)], $reserva)) {
            return false;
        }

        $reserva->update(['comprobante_enviado_en' => now()]);

        return true;
    }

    // Pago que no termino en una compra confirmada: pendiente, rechazado o devuelto.
    public function avisarPago(Reserva $reserva, Pago $pago): bool
    {
        $evento = match ($pago->estado) {
            'pendiente' => 'pago_pendiente',
            'rechazado' => 'pago_rechazado',
            'reembolsado' => 'compra_devuelta',
            default => null,
        };

        if (! $evento) {
            return false;
        }

        // Hasta cuando puede volver a intentar el pago (mientras sigan bloqueadas sus butacas).
        $expira = $reserva->estado === 'pendiente' ? $reserva->reservaAsientos()->max('expira_en') : null;

        return $this->post([
            'evento' => $evento,
            ...$this->comprobantes->resumen($reserva),
            'pago' => [
                'estado' => $pago->estado,
                'metodo' => $pago->metodo,
                'monto' => (float) $pago->monto,
                'detalle' => $pago->detalle,
                'motivo' => $pago->estado === 'pendiente'
                    ? 'Mercado Pago está revisando el pago. Te avisamos apenas se acredite.'
                    : $pago->motivo(),
            ],
            'reintentar_hasta' => $evento === 'pago_rechazado' && $expira && Carbon::parse($expira)->isFuture()
                ? Carbon::parse($expira)->setTimezone(ComprobanteService::ZONA)->format('H:i')
                : null,
        ], $reserva);
    }

    private function post(array $payload, Reserva $reserva): bool
    {
        $webhook = config('services.n8n.compra_webhook');

        if (! $webhook) {
            Log::warning("N8N_COMPRA_WEBHOOK no esta configurado: no se envio {$payload['evento']} de la reserva {$reserva->id}");

            return false;
        }

        try {
            // n8n valida este header con una credencial "Header Auth" (N8N_WEBHOOK_TOKEN).
            Http::timeout(8)
                ->withHeaders(['X-Cine-Token' => (string) config('services.n8n.token')])
                ->post($webhook, $payload)
                ->throw();

            return true;
        } catch (Throwable $e) {
            Log::error("No se pudo enviar {$payload['evento']} de la reserva {$reserva->id} a n8n: ".$e->getMessage());

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
