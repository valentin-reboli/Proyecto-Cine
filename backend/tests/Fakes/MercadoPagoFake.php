<?php

namespace Tests\Fakes;

use App\Models\Reserva;
use App\Services\MercadoPagoService;

// Mercado Pago simulado para los tests: nunca se llama a la API real.
// Como en el sandbox, el resultado lo decide el token: APRO aprueba, CONT queda pendiente y OTHE rechaza.
class MercadoPagoFake extends MercadoPagoService
{
    public array $pagos = [];

    public array $reembolsos = [];

    public array $preferencias = [];

    private int $siguienteId = 1000;

    public function configurado(): bool
    {
        return true;
    }

    public function cobrarConTarjeta(Reserva $reserva, array $tarjeta): array
    {
        [$status, $detalle] = match ($tarjeta['token']) {
            'APRO' => ['approved', 'accredited'],
            'CONT' => ['in_process', 'pending_contingency'],
            'FUND' => ['rejected', 'cc_rejected_insufficient_amount'],
            default => ['rejected', 'cc_rejected_other_reason'],
        };

        return $this->agregarPago($reserva->id, (float) $reserva->total, $status, $detalle, $tarjeta['payment_method_id']);
    }

    public function crearPreferencia(Reserva $reserva, string $urlRetorno): array
    {
        $this->preferencias[] = ['reserva_id' => $reserva->id, 'monto' => (float) $reserva->total, 'url_retorno' => $urlRetorno];

        return ['id' => 'pref-'.$reserva->id, 'url' => 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-'.$reserva->id];
    }

    public function obtenerPago(string $id): array
    {
        return $this->pagos[$id];
    }

    public function reembolsar(string $id): void
    {
        $this->reembolsos[] = $id;
        $this->pagos[$id]['status'] = 'refunded';
    }

    // Simula un pago hecho en Checkout Pro (o que Mercado Pago cambio de estado).
    public function agregarPago(int $reservaId, float $monto, string $status = 'approved', string $detalle = 'accredited', string $metodo = 'account_money'): array
    {
        $id = (string) $this->siguienteId++;

        return $this->pagos[$id] = [
            'id' => $id,
            'status' => $status,
            'status_detail' => $detalle,
            'external_reference' => (string) $reservaId,
            'transaction_amount' => $monto,
            'payment_method_id' => $metodo,
        ];
    }
}
