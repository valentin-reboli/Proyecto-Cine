<?php

namespace Tests;

use App\Models\Reserva;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\MercadoPagoFake;

abstract class TestCase extends BaseTestCase
{
    protected MercadoPagoFake $mercadoPago;

    protected function setUp(): void
    {
        parent::setUp();

        // Los tests nunca llaman a la API real de Mercado Pago.
        $this->mercadoPago = new MercadoPagoFake;
        $this->app->instance(MercadoPagoService::class, $this->mercadoPago);
    }

    // Simula que el comprador pago en Mercado Pago (Checkout Pro) y volvio a la web,
    // que verifica el pago con el servidor (igual que el front).
    protected function pagar(int $reservaId, string $status = 'approved', string $detalle = 'accredited', string $correo = 'ana@test.com')
    {
        $monto = $this->mercadoPago->montoACobrar(Reserva::findOrFail($reservaId));
        $pago = $this->mercadoPago->agregarPago($reservaId, $monto, $status, $detalle);

        return $this->postJson("/api/reservas/{$reservaId}/pago/verificar", ['correo' => $correo, 'pago_id' => $pago['id']]);
    }
}
