<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    // Confirma el pago de una reserva pendiente: genera el codigo_qr,
    // pasa las butacas de temporal a ocupado y registra el pago.
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
