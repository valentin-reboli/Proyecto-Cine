<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\ReservaAsiento;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Payment\PaymentRefundClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;
use Throwable;

// Habla con la API de Mercado Pago usando el SDK oficial (mercadopago/dx-php).
// Con credenciales de prueba (MP_ACCESS_TOKEN de la cuenta de test) no se mueve dinero real.
// Devuelve los pagos como arrays simples para que el resto de la app no dependa del SDK.
class MercadoPagoService
{
    public function configurado(): bool
    {
        return (bool) config('services.mercadopago.access_token') && (bool) config('services.mercadopago.public_key');
    }

    // Cobra con la tarjeta tokenizada por el Card Payment Brick del front.
    // El monto sale de la reserva (no del front) y la reserva va en external_reference.
    public function cobrarConTarjeta(Reserva $reserva, array $tarjeta): array
    {
        $this->configurar();

        $opciones = new RequestOptions;
        // Si el request se repite (doble click, reintento de red) Mercado Pago no cobra dos veces.
        $opciones->setCustomHeaders(['X-Idempotency-Key: reserva-'.$reserva->id.'-'.$tarjeta['token']]);

        $pago = $this->llamar(fn () => (new PaymentClient)->create(array_filter([
            'transaction_amount' => (float) $reserva->total,
            'token' => $tarjeta['token'],
            'description' => 'Cine Concordia - Reserva Nº '.$reserva->id,
            'installments' => (int) $tarjeta['installments'],
            'payment_method_id' => $tarjeta['payment_method_id'],
            'issuer_id' => $tarjeta['issuer_id'] ?? null,
            'external_reference' => (string) $reserva->id,
            'notification_url' => $this->urlWebhook(),
            'payer' => array_filter([
                'email' => $tarjeta['payer']['email'] ?? $reserva->invitado_correo,
                'identification' => $tarjeta['payer']['identification'] ?? null,
            ]),
        ], fn ($valor) => $valor !== null), $opciones));

        return $this->normalizar($pago);
    }

    // Arma una preferencia de Checkout Pro para pagar con la cuenta de Mercado Pago.
    // Vence junto con el bloqueo de las butacas.
    public function crearPreferencia(Reserva $reserva, string $urlRetorno): array
    {
        $this->configurar();

        $vence = ReservaAsiento::where('reserva_id', $reserva->id)->min('expira_en');
        $vuelta = $urlRetorno.(str_contains($urlRetorno, '?') ? '&' : '?').'reserva='.$reserva->id;

        $preferencia = $this->llamar(fn () => (new PreferenceClient)->create(array_filter([
            'items' => [[
                'id' => 'reserva-'.$reserva->id,
                'title' => 'Cine Concordia - Reserva Nº '.$reserva->id,
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => (float) $reserva->total,
            ]],
            'payer' => ['email' => $reserva->invitado_correo],
            'external_reference' => (string) $reserva->id,
            'back_urls' => ['success' => $vuelta, 'failure' => $vuelta, 'pending' => $vuelta],
            'auto_return' => 'approved',
            'notification_url' => $this->urlWebhook(),
            'expires' => $vence !== null,
            'expiration_date_to' => $vence ? now()->parse($vence)->toIso8601String() : null,
        ], fn ($valor) => $valor !== null)));

        return [
            'id' => $preferencia->id,
            // Con credenciales de prueba el link de pago es el de sandbox.
            'url' => $preferencia->sandbox_init_point ?: $preferencia->init_point,
        ];
    }

    // Consulta un pago en Mercado Pago: es la unica fuente confiable de su estado.
    public function obtenerPago(string $id): array
    {
        $this->configurar();

        return $this->normalizar($this->llamar(fn () => (new PaymentClient)->get((int) $id)));
    }

    // Devuelve el dinero de un pago aprobado que no se pudo usar (por ejemplo, llego tarde).
    public function reembolsar(string $id): void
    {
        $this->configurar();

        $this->llamar(fn () => (new PaymentRefundClient)->refundTotal((int) $id));
    }

    private function configurar(): void
    {
        $token = config('services.mercadopago.access_token');

        if (! $token) {
            throw new RuntimeException('Mercado Pago no esta configurado (falta MP_ACCESS_TOKEN).');
        }

        MercadoPagoConfig::setAccessToken($token);
    }

    private function llamar(callable $llamada): mixed
    {
        try {
            return $llamada();
        } catch (MPApiException $e) {
            $respuesta = $e->getApiResponse();
            $detalle = $respuesta ? json_encode($respuesta->getContent()) : $e->getMessage();

            throw new RuntimeException('Mercado Pago respondio '.$e->getStatusCode().': '.$detalle, 0, $e);
        } catch (Throwable $e) {
            // Red caida, timeout, credenciales mal formadas: tampoco se puede saber el estado del pago.
            throw new RuntimeException('No se pudo contactar a Mercado Pago: '.$e->getMessage(), 0, $e);
        }
    }

    private function urlWebhook(): ?string
    {
        $url = rtrim((string) config('app.url'), '/').'/api/pagos/mercadopago/webhook';

        // Mercado Pago no acepta notificaciones a localhost: en local se confirma consultando el pago.
        return str_contains($url, 'localhost') || str_contains($url, '127.0.0.1') ? null : $url;
    }

    private function normalizar(object $pago): array
    {
        return [
            'id' => (string) $pago->id,
            'status' => $pago->status,
            'status_detail' => $pago->status_detail,
            'external_reference' => $pago->external_reference,
            'transaction_amount' => (float) $pago->transaction_amount,
            'payment_method_id' => $pago->payment_method_id,
        ];
    }
}
