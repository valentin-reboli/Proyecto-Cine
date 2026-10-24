<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Descuento;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use App\Services\CompraNotifier;
use App\Services\DescuentoService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PagoController extends Controller
{
    // Confirma el pago de una reserva pendiente: genera el codigo_qr,
    // pasa las butacas de temporal a ocupado y registra el pago.
    #[OA\Post(
        path: '/api/reservas/{reserva}/pago',
        summary: 'Confirmar el pago de una reserva',
        description: 'Genera el codigo QR, pasa las butacas de temporal a ocupado y registra el pago. Solo con el correo de la reserva.',
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo', 'metodo'],
                properties: [
                    new OA\Property(property: 'correo', type: 'string', format: 'email', example: 'ana@x.com'),
                    new OA\Property(property: 'metodo', type: 'string', maxLength: 30, example: 'tarjeta'),
                    new OA\Property(property: 'comprobante', type: 'string', maxLength: 255, nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pago registrado, reserva pagada con codigo QR.'),
            new OA\Response(response: 403, description: 'El correo no coincide con el de la reserva.'),
            new OA\Response(response: 410, description: 'La reserva vencio y sus butacas fueron liberadas.'),
            new OA\Response(response: 422, description: 'La reserva no esta pendiente de pago.'),
        ]
    )]
    public function store(Request $request, Reserva $reserva, CompraNotifier $notificador, DescuentoService $descuentos)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
            'metodo' => ['required', 'string', 'max:30'],
            'comprobante' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($reserva->invitado_correo && strcasecmp($reserva->invitado_correo, $data['correo']) === 0, 403);

        // Si se termino el tiempo pero el cron todavia no paso, se libera ahora.
        ReservaAsiento::liberarVencidas($reserva->funcion_id);
        $reserva->refresh();

        if ($reserva->estado === 'expirada') {
            return response()->json([
                'message' => 'Se termino el tiempo para completar la compra y las butacas se liberaron. Hace una reserva nueva.',
            ], 410);
        }

        if ($reserva->estado !== 'pendiente') {
            return response()->json([
                'message' => 'La reserva no esta pendiente de pago (estado actual: '.$reserva->estado.').',
            ], 422);
        }

        // Dentro de la transaccion y con la fila de la reserva bloqueada: dos pagos
        // simultaneos, o un pago que se cruza con la liberacion del bloqueo, se
        // resuelven de a uno.
        $pago = DB::transaction(function () use ($reserva, $data) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->first();

            if ($reserva->estado !== 'pendiente') {
                throw new HttpResponseException(response()->json([
                    'message' => 'La reserva no esta pendiente de pago (estado actual: '.$reserva->estado.').',
                ], 422));
            }

            // Solo se ocupan butacas cuyo bloqueo sigue vigente; si alguna vencio se cancela todo.
            $totalButacas = ReservaAsiento::where('reserva_id', $reserva->id)->count();

            $ocupadas = ReservaAsiento::where('reserva_id', $reserva->id)
                ->where('estado_bloqueo', 'temporal')
                ->where('expira_en', '>', now())
                ->update(['estado_bloqueo' => 'ocupado']);

            if ($ocupadas !== $totalButacas) {
                throw new HttpResponseException(response()->json([
                    'message' => 'La reserva vencio y sus butacas fueron liberadas. Hace una reserva nueva.',
                ], 410));
            }

            // El codigo de descuento se consume recien al pagar (y una sola vez).
            if ($reserva->descuento_id) {
                $consumido = Descuento::where('id', $reserva->descuento_id)
                    ->whereNull('usado_en')
                    ->update(['usado_en' => now()]);

                if ($consumido !== 1) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'El código de descuento ya fue usado. Hacé una reserva nueva sin ese código.',
                    ], 422));
                }
            }

            $reserva->update([
                'estado' => 'pagada',
                'codigo_qr' => (string) Str::uuid(),
            ]);

            return Pago::create([
                'reserva_id' => $reserva->id,
                'metodo' => $data['metodo'],
                'estado' => 'aprobado',
                'comprobante' => $data['comprobante'] ?? null,
                'monto' => $reserva->total,
                'pagado_en' => now(),
            ]);
        }, 3);

        $reserva->refresh();

        // Descuento de regalo para la proxima compra + comprobante y QR por mensaje (n8n).
        $descuentos->generarPara($reserva);
        $notificador->enviar($reserva->fresh());

        return response()->json(
            $pago->load(['reserva.funcion.pelicula', 'reserva.reservaAsientos.asiento']),
            201
        );
    }
}
