<?php

namespace Tests\Fakes;

use App\Models\Reserva;
use App\Services\MercadoPagoService;
use RuntimeException;

// Mercado Pago simulado para los tests: nunca se llama a la API real.
// Los pagos se cargan con agregarPago() (lo que el comprador haria en Checkout Pro).
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

    public function crearPreferencia(Reserva $reserva, string $urlRetorno): array
    {
        $this->preferencias[] = ['reserva_id' => $reserva->id, 'monto' => $this->montoACobrar($reserva), 'url_retorno' => $urlRetorno];

        return ['id' => 'pref-'.$reserva->id, 'url' => 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-'.$reserva->id];
    }

    public function obtenerPago(string $id): array
    {
        if (! isset($this->pagos[$id])) {
            throw new RuntimeException('Mercado Pago respondio 404: Payment not found');
        }

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
