<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\Cliente;
use Illuminate\Support\Facades\Http;
use Throwable;

// Entrega la campaña a n8n (N8N_CAMPANIAS_WEBHOOK), que es quien manda los mensajes
// por correo, WhatsApp o Telegram. Solo se incluyen clientes que aceptaron recibir publicidad.
class CampaniaSender
{
    public function enviar(Campania $campania): Campania
    {
        $webhook = config('services.n8n.campanias_webhook');

        $clientes = Cliente::where('acepta_marketing', true)
            ->when($campania->canal === 'email', fn ($q) => $q->whereNotNull('correo'))
            ->when($campania->canal !== 'email', fn ($q) => $q->whereNotNull('telefono'))
            ->get(['nombre', 'correo', 'telefono']);

        try {
            if (! $webhook) {
                throw new \RuntimeException('Falta configurar N8N_CAMPANIAS_WEBHOOK.');
            }
            if ($clientes->isEmpty()) {
                throw new \RuntimeException('No hay clientes con permiso de publicidad para este canal.');
            }

            Http::timeout(30)->post($webhook, [
                'campania' => $campania->only(['id', 'nombre', 'canal', 'asunto', 'mensaje']),
                'destinatarios' => $clientes,
            ])->throw();

            $campania->update([
                'estado' => 'enviada',
                'destinatarios' => $clientes->count(),
                'enviada_en' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $campania->update(['estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        return $campania->fresh();
    }
}
