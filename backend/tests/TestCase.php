<?php

namespace Tests;

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

    // Datos de tarjeta como los manda el Card Payment Brick (token APRO = pago aprobado).
    protected function tarjeta(string $correo = 'ana@test.com', string $token = 'APRO'): array
    {
        return ['correo' => $correo, 'token' => $token, 'payment_method_id' => 'visa', 'installments' => 1];
    }
}
