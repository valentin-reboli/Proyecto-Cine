<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
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
    public function store(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
            'metodo' => ['required', 'string', 'max:30'],
            'comprobante' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(strcasecmp($reserva->invitado_correo, $data['correo']) === 0, 403);

        if ($reserva->estado !== 'pendiente') {
            return response()->json([
                'message' => 'La reserva no esta pendiente de pago (estado actual: '.$reserva->estado.').',
            ], 422);
        }

        $butacasVigentes = ReservaAsiento::where('reserva_id', $reserva->id)
            ->where('estado_bloqueo', 'temporal')
            ->count();

        $totalButacas = ReservaAsiento::where('reserva_id', $reserva->id)->count();

        if ($butacasVigentes !== $totalButacas) {
            return response()->json([
                'message' => 'La reserva vencio y sus butacas fueron liberadas. Hace una reserva nueva.',
            ], 410);
        }

        $pago = DB::transaction(function () use ($request, $reserva, $data) {
            $reserva->update([
                'estado' => 'pagada',
                'codigo_qr' => (string) Str::uuid(),
            ]);

            ReservaAsiento::where('reserva_id', $reserva->id)
                ->where('estado_bloqueo', 'temporal')
                ->update(['estado_bloqueo' => 'ocupado']);

            return Pago::create([
                'reserva_id' => $reserva->id,
                'metodo' => $data['metodo'],
                'estado' => 'aprobado',
                'comprobante' => $data['comprobante'] ?? null,
                'monto' => $reserva->total,
                'pagado_en' => now(),
            ]);
        });

        return response()->json(
            $pago->load(['reserva.funcion.pelicula', 'reserva.reservaAsientos.asiento']),
            201
        );
    }
}
