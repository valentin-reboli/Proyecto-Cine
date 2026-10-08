<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EntradaController extends Controller
{
    // Control de acceso en la puerta: el personal escanea el QR de la
    // entrada y, si es valida, queda marcada como usada.
    #[OA\Post(
        path: '/api/entradas/validar',
        summary: 'Validar una entrada por su QR',
        description: 'Busca la reserva por su codigo QR. Si esta pagada, la funcion no fue cancelada y no se uso antes, la marca como usada.',
        tags: ['Entradas'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo_qr'],
                properties: [
                    new OA\Property(property: 'codigo_qr', type: 'string', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Entrada valida, marcada como usada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No existe una entrada con ese codigo.'),
            new OA\Response(response: 409, description: 'La entrada ya fue usada.'),
            new OA\Response(response: 422, description: 'La reserva no esta pagada o la funcion fue cancelada.'),
        ]
    )]
    public function validar(Request $request)
    {
        $data = $request->validate([
            'codigo_qr' => ['required', 'string', 'max:255'],
        ]);

        $reserva = Reserva::where('codigo_qr', $data['codigo_qr'])
            ->with('funcion')
            ->first();

        if (! $reserva) {
            return response()->json(['message' => 'No existe una entrada con ese codigo.'], 404);
        }

        if ($reserva->estado !== 'pagada') {
            return response()->json([
                'message' => 'La reserva no esta pagada (estado actual: '.$reserva->estado.').',
            ], 422);
        }

        if ($reserva->funcion->cancelada) {
            return response()->json(['message' => 'La funcion de esta entrada fue cancelada.'], 422);
        }

        // Solo se marca si todavia no se uso: si dos escaneos llegan juntos,
        // uno solo actualiza la fila y el otro cae en el 409.
        $marcada = Reserva::whereKey($reserva->id)
            ->whereNull('usada_en')
            ->update(['usada_en' => now()]);

        if (! $marcada) {
            return response()->json([
                'message' => 'La entrada ya fue usada.',
                'usada_en' => $reserva->fresh()->usada_en,
            ], 409);
        }

        return $reserva->fresh()->load(['funcion.pelicula', 'funcion.sala', 'reservaAsientos.asiento']);
    }
}
