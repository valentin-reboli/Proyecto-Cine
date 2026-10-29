<?php

namespace App\Services;

use App\Models\Reserva;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Avisa a n8n (N8N_COMPRA_WEBHOOK) de una compra pagada para que mande el comprobante
// y el QR al comprador (correo, Telegram, etc). Si falla, la compra igual queda confirmada.
class CompraNotifier
{
    public function enviar(Reserva $reserva): bool
    {
        $webhook = config('services.n8n.compra_webhook');

        if (! $webhook) {
            Log::warning('N8N_COMPRA_WEBHOOK no esta configurado: no se envio el comprobante de la reserva '.$reserva->id);

            return false;
        }

        $reserva->load(['funcion.pelicula', 'funcion.sala', 'funcion.formato', 'reservaAsientos.asiento', 'descuentoGenerado', 'combos']);
        $funcion = $reserva->funcion;
        $butacas = $reserva->reservaAsientos->map(fn ($ra) => $ra->asiento->fila.$ra->asiento->numero)->sort()->values();
        // APP_URL (no el host del request): detras de Caddy el host que ve Laravel es interno.
        $qrUrl = rtrim(config('app.url'), '/').'/api/entradas/qr/'.$reserva->codigo_qr.'.png';
        $momento = Carbon::parse($funcion->fecha_hora);
        $fecha = $momento->format('d/m/Y');
        $hora = $momento->format('H:i');

        $descuento = $reserva->descuentoGenerado;

        $mensaje = "Hola {$reserva->invitado_nombre}! Tu compra en Cine Concordia está confirmada.\n"
            ."Película: {$funcion->pelicula->titulo}\n"
            ."Función: {$fecha} {$hora} · {$funcion->sala->nombre} · {$funcion->formato->nombre}\n"
            .'Butacas: '.$butacas->implode(', ')."\n"
            .($reserva->combos->isNotEmpty() ? 'Combos: '.$reserva->combos->map(fn ($c) => "{$c->cantidad}x {$c->nombre}")->implode(', ')."\n" : '')
            .'Total: $'.number_format((float) $reserva->total, 0, ',', '.')."\n"
            ."Reserva Nº {$reserva->id}\n"
            .'Presentá el código QR en la entrada del cine.'
            .($descuento ? "\n\nTu regalo: {$descuento->porcentaje}% de descuento en tu próxima compra con el código {$descuento->codigo} (válido hasta ".$descuento->vence_en->format('d/m/Y').'). Canjealo en la web al pagar.' : '');

        try {
            Http::timeout(15)->post($webhook, [
                'evento' => 'compra_confirmada',
                'reserva' => [
                    'id' => $reserva->id,
                    'codigo' => $reserva->codigo_qr,
                    'total' => $reserva->total,
                ],
                'comprador' => [
                    'nombre' => $reserva->invitado_nombre,
                    'correo' => $reserva->invitado_correo,
                ],
                'pelicula' => $funcion->pelicula->titulo,
                'funcion' => [
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'sala' => $funcion->sala->nombre,
                    'formato' => $funcion->formato->nombre,
                    'idioma' => $funcion->idioma,
                ],
                'butacas' => $butacas->all(),
                'combos' => $reserva->combos->map(fn ($c) => ['nombre' => $c->nombre, 'cantidad' => $c->cantidad, 'precio_unitario' => $c->precio_unitario])->values()->all(),
                'descuento' => $descuento ? [
                    'codigo' => $descuento->codigo,
                    'porcentaje' => $descuento->porcentaje,
                    'vence' => $descuento->vence_en->format('d/m/Y'),
                ] : null,
                'qr_url' => $qrUrl,
                'mensaje' => $mensaje,
            ])->throw();

            $reserva->update(['comprobante_enviado_en' => now()]);

            return true;
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el comprobante de la reserva '.$reserva->id.': '.$e->getMessage());

            return false;
        }
    }
}
