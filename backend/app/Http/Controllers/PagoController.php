<?php

namespace App\Http\Controllers;

use App\Models\Descuento;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use App\Services\MercadoPagoService;
use App\Services\PagoService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use RuntimeException;

// Pagos con Mercado Pago (credenciales de prueba: no se cobra dinero real).
// El front nunca confirma un pago: el estado siempre se consulta a Mercado Pago desde el servidor.
class PagoController extends Controller
{
    public function __construct(
        private MercadoPagoService $mercadoPago,
        private PagoService $pagos,
    ) {}

    // Clave publica para el SDK de Mercado Pago del front (no es secreta).
    #[OA\Get(
        path: '/api/pagos/config',
        summary: 'Configuracion publica de Mercado Pago',
        tags: ['Pagos'],
        responses: [new OA\Response(response: 200, description: 'Clave publica, si los pagos estan habilitados y si es modo prueba.')]
    )]
    public function config()
    {
        return response()->json([
            'habilitado' => $this->mercadoPago->configurado(),
            'public_key' => config('services.mercadopago.public_key'),
            'modo_prueba' => (bool) config('services.mercadopago.modo_prueba'),
        ]);
    }

    // Cobra la reserva con la tarjeta que tokenizo el Card Payment Brick en el navegador.
    // El monto lo pone el servidor (el total de la reserva) y el resultado lo informa Mercado Pago.
    #[OA\Post(
        path: '/api/reservas/{reserva}/pago',
        summary: 'Pagar una reserva con tarjeta (Mercado Pago)',
        description: 'Cobra el total de la reserva con el token de tarjeta generado por el SDK de Mercado Pago. Si Mercado Pago lo aprueba, las butacas pasan de temporal a ocupado y se genera el codigo QR. Solo con el correo de la reserva.',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo', 'token', 'payment_method_id', 'installments'],
                properties: [
                    new OA\Property(property: 'correo', type: 'string', format: 'email', example: 'ana@x.com'),
                    new OA\Property(property: 'token', type: 'string', description: 'Token de la tarjeta (Card Payment Brick).'),
                    new OA\Property(property: 'payment_method_id', type: 'string', example: 'visa'),
                    new OA\Property(property: 'installments', type: 'integer', example: 1),
                    new OA\Property(property: 'issuer_id', type: 'string', nullable: true),
                    new OA\Property(property: 'payer', type: 'object', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pago aprobado, reserva pagada con codigo QR.'),
            new OA\Response(response: 202, description: 'El pago quedo pendiente de revision en Mercado Pago.'),
            new OA\Response(response: 402, description: 'Mercado Pago rechazo el pago.'),
            new OA\Response(response: 403, description: 'El correo no coincide con el de la reserva.'),
            new OA\Response(response: 410, description: 'La reserva vencio y sus butacas fueron liberadas.'),
            new OA\Response(response: 422, description: 'La reserva no esta pendiente de pago.'),
            new OA\Response(response: 503, description: 'Mercado Pago no esta configurado o no responde.'),
        ]
    )]
    public function store(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
            'token' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', 'string', 'max:50'],
            'installments' => ['required', 'integer', 'min:1', 'max:36'],
            'issuer_id' => ['nullable'],
            'payer' => ['nullable', 'array'],
            'payer.email' => ['nullable', 'email'],
            'payer.identification.type' => ['nullable', 'string', 'max:20'],
            'payer.identification.number' => ['nullable', 'string', 'max:30'],
        ]);

        $this->validarPagable($reserva, $data['correo']);

        $pago = $this->conMercadoPago(fn () => $this->pagos->registrar(
            $reserva,
            $this->mercadoPago->cobrarConTarjeta($reserva, $data)
        ));

        return $this->respuesta($pago);
    }

    // Checkout Pro: arma la preferencia y devuelve el link de Mercado Pago al que se redirige al comprador.
    #[OA\Post(
        path: '/api/reservas/{reserva}/pago/preferencia',
        summary: 'Crear el link de pago de Mercado Pago (Checkout Pro)',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo'],
                properties: [new OA\Property(property: 'correo', type: 'string', format: 'email')]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Preferencia creada: id y url de pago.'),
            new OA\Response(response: 403, description: 'El correo no coincide con el de la reserva.'),
            new OA\Response(response: 410, description: 'La reserva vencio y sus butacas fueron liberadas.'),
            new OA\Response(response: 422, description: 'La reserva no esta pendiente de pago.'),
        ]
    )]
    public function preferencia(Request $request, Reserva $reserva)
    {
        $data = $request->validate(['correo' => ['required', 'email']]);

        $this->validarPagable($reserva, $data['correo']);

        $preferencia = $this->conMercadoPago(fn () => $this->mercadoPago->crearPreferencia(
            $reserva,
            config('services.mercadopago.url_retorno')
        ));

        return response()->json($preferencia, 201);
    }

    // Al volver de Checkout Pro el front manda el payment_id que puso Mercado Pago en la URL.
    // El estado no se toma de la URL: se consulta el pago en Mercado Pago.
    #[OA\Post(
        path: '/api/reservas/{reserva}/pago/verificar',
        summary: 'Verificar el pago de Checkout Pro al volver de Mercado Pago',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo'],
                properties: [
                    new OA\Property(property: 'correo', type: 'string', format: 'email'),
                    new OA\Property(property: 'pago_id', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pago aprobado, reserva pagada con codigo QR.'),
            new OA\Response(response: 202, description: 'El pago quedo pendiente.'),
            new OA\Response(response: 402, description: 'El pago fue rechazado o no se completo.'),
            new OA\Response(response: 403, description: 'El correo no coincide con el de la reserva.'),
            new OA\Response(response: 410, description: 'Se pago tarde: las butacas se liberaron y se devuelve el dinero.'),
        ]
    )]
    public function verificar(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
            'pago_id' => ['nullable', 'regex:/^\d{1,20}$/'],
        ]);

        $this->validarCorreo($reserva, $data['correo']);

        if (empty($data['pago_id'])) {
            // Volvio sin pagar. Si el webhook ya lo confirmo, se informa ese pago.
            $aprobado = $reserva->pagos()->where('estado', 'aprobado')->first();

            return $aprobado ? $this->respuesta($aprobado) : response()->json([
                'message' => 'El pago no se completó. Podés intentarlo de nuevo mientras sigan reservadas tus butacas.',
                'estado' => 'rechazado',
            ], 402);
        }

        $pago = $this->conMercadoPago(function () use ($reserva, $data) {
            $mp = $this->mercadoPago->obtenerPago($data['pago_id']);

            if ((string) $mp['external_reference'] !== (string) $reserva->id) {
                throw new HttpResponseException(response()->json(['message' => 'Ese pago no corresponde a esta reserva.'], 422));
            }

            return $this->pagos->registrar($reserva, $mp);
        });

        return $this->respuesta($pago);
    }

    // Notificaciones de Mercado Pago (webhook). Solo se usa el id: el pago se consulta a la API.
    #[OA\Post(
        path: '/api/pagos/mercadopago/webhook',
        summary: 'Webhook de notificaciones de Mercado Pago',
        tags: ['Pagos'],
        responses: [
            new OA\Response(response: 200, description: 'Notificacion recibida.'),
            new OA\Response(response: 401, description: 'Firma x-signature invalida.'),
        ]
    )]
    public function webhook(Request $request)
    {
        // Formato actual: ?type=payment&data.id=123 (PHP convierte data.id en data_id) o en el body.
        $tipo = $request->input('type') ?? $request->query('topic');
        $id = (string) ($request->query('data_id') ?? $request->input('data.id') ?? $request->query('id'));

        if (! $this->firmaValida($request, $id)) {
            return response()->json(['message' => 'Firma invalida.'], 401);
        }

        if ($tipo !== 'payment' || ! preg_match('/^\d{1,20}$/', $id)) {
            return response()->json(['ok' => true]);
        }

        try {
            $mp = $this->mercadoPago->obtenerPago($id);
            $reserva = Reserva::find($mp['external_reference']);

            if ($reserva) {
                $this->pagos->registrar($reserva, $mp);
            }
        } catch (RuntimeException $e) {
            Log::error('Webhook de Mercado Pago (pago '.$id.'): '.$e->getMessage());

            // Mercado Pago reintenta la notificacion si no recibe 200.
            return response()->json(['message' => 'No se pudo procesar.'], 500);
        }

        return response()->json(['ok' => true]);
    }

    private function validarCorreo(Reserva $reserva, string $correo): void
    {
        abort_unless($reserva->invitado_correo && strcasecmp($reserva->invitado_correo, $correo) === 0, 403);
    }

    // Antes de cobrar: la reserva tiene que estar pendiente y con todas sus butacas bloqueadas.
    private function validarPagable(Reserva $reserva, string $correo): void
    {
        $this->validarCorreo($reserva, $correo);

        // Si se termino el tiempo pero el cron todavia no paso, se libera ahora.
        ReservaAsiento::liberarVencidas($reserva->funcion_id);
        $reserva->refresh();

        if ($reserva->estado === 'expirada') {
            throw new HttpResponseException(response()->json([
                'message' => 'Se termino el tiempo para completar la compra y las butacas se liberaron. Hace una reserva nueva.',
            ], 410));
        }

        if ($reserva->estado !== 'pendiente') {
            throw new HttpResponseException(response()->json([
                'message' => 'La reserva no esta pendiente de pago (estado actual: '.$reserva->estado.').',
            ], 422));
        }

        // Si el codigo de descuento ya se uso en otra compra, no se cobra.
        if ($reserva->descuento_id && Descuento::whereKey($reserva->descuento_id)->whereNotNull('usado_en')->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => 'El código de descuento ya fue usado. Hacé una reserva nueva sin ese código.',
            ], 422));
        }

        if (! $this->mercadoPago->configurado()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Los pagos con Mercado Pago no estan configurados. Avisanos para completar tu compra.',
            ], 503));
        }
    }

    private function conMercadoPago(callable $llamada): mixed
    {
        try {
            return $llamada();
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            Log::error('Error con Mercado Pago: '.$e->getMessage());

            // En modo prueba se muestra el motivo que dio Mercado Pago para poder corregir la configuracion.
            $detalle = config('services.mercadopago.modo_prueba') ? ' ('.$e->getMessage().')' : '';

            throw new HttpResponseException(response()->json([
                'message' => 'No pudimos procesar el pago con Mercado Pago. Revisá los datos e intentá de nuevo.'.$detalle,
            ], 503));
        }
    }

    private function respuesta(Pago $pago)
    {
        return match ($pago->estado) {
            'aprobado' => response()->json(
                $pago->load(['reserva.funcion.pelicula', 'reserva.reservaAsientos.asiento']),
                201
            ),
            'pendiente' => response()->json([
                'message' => 'Mercado Pago está revisando el pago. Te avisamos por correo apenas se acredite.',
                'estado' => 'pendiente',
            ], 202),
            'reembolsado' => response()->json([
                'message' => $pago->detalle === 'bloqueo_vencido' || $pago->detalle === 'reserva_expirada'
                    ? 'El pago llegó cuando ya se había terminado el tiempo y las butacas se liberaron. Te devolvimos el dinero.'
                    : 'No pudimos completar la compra con ese pago. Te devolvimos el dinero.',
                'estado' => 'reembolsado',
            ], 410),
            default => response()->json([
                'message' => $this->motivoRechazo($pago->detalle),
                'estado' => 'rechazado',
            ], 402),
        };
    }

    // Mensajes para los rechazos mas comunes de tarjeta (status_detail de Mercado Pago).
    private function motivoRechazo(?string $detalle): string
    {
        return match ($detalle) {
            'cc_rejected_insufficient_amount' => 'La tarjeta no tiene fondos suficientes.',
            'cc_rejected_bad_filled_security_code' => 'El código de seguridad es incorrecto.',
            'cc_rejected_bad_filled_date' => 'La fecha de vencimiento es incorrecta.',
            'cc_rejected_bad_filled_card_number', 'cc_rejected_bad_filled_other' => 'Revisá los datos de la tarjeta.',
            'cc_rejected_call_for_authorize' => 'Tenés que autorizar el pago con el banco de tu tarjeta.',
            'cc_rejected_card_disabled' => 'La tarjeta está deshabilitada. Llamá a tu banco para activarla.',
            'cc_rejected_duplicated_payment' => 'Ya hiciste un pago por ese monto. Si necesitás volver a pagar, usá otra tarjeta.',
            'cc_rejected_high_risk' => 'Mercado Pago rechazó el pago. Probá con otro medio de pago.',
            'cc_rejected_max_attempts' => 'Llegaste al límite de intentos con esa tarjeta. Probá con otra.',
            default => 'Mercado Pago rechazó el pago. Probá con otra tarjeta o medio de pago.',
        };
    }

    // Valida la firma x-signature de Mercado Pago si hay MP_WEBHOOK_SECRET configurado.
    private function firmaValida(Request $request, string $id): bool
    {
        $secreto = config('services.mercadopago.webhook_secret');

        if (! $secreto) {
            return true;
        }

        $partes = collect(explode(',', (string) $request->header('x-signature')))
            ->mapWithKeys(function ($parte) {
                [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, '');

                return [$clave => $valor];
            });

        if (! $partes->get('ts') || ! $partes->get('v1')) {
            return false;
        }

        $manifiesto = 'id:'.strtolower($id).';request-id:'.$request->header('x-request-id').';ts:'.$partes->get('ts').';';

        return hash_equals(hash_hmac('sha256', $manifiesto, $secreto), $partes->get('v1'));
    }
}
