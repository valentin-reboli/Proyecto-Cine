<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Funcion;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->reservas()
            ->with(['funcion.pelicula', 'reservaAsientos.asiento'])
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'funcion_id' => ['required', 'integer', 'exists:funciones,id'],
            'asientos' => ['required', 'array', 'min:1'],
            'asientos.*' => ['integer', 'distinct', 'exists:asientos,id'],
        ]);

        $funcion = Funcion::findOrFail($data['funcion_id']);

        if ($funcion->cancelada) {
            return response()->json(['message' => 'La funcion fue cancelada.'], 422);
        }

        $asientos = Asiento::whereIn('id', $data['asientos'])->get();

        if ($asientos->contains(fn (Asiento $asiento) => $asiento->sala_id !== $funcion->sala_id)) {
            return response()->json([
                'message' => 'Hay butacas que no pertenecen a la sala de esta funcion.',
            ], 422);
        }

        try {
            $reserva = DB::transaction(function () use ($request, $funcion, $asientos) {
                $reserva = Reserva::create([
                    'user_id' => $request->user()->id,
                    'funcion_id' => $funcion->id,
                    'total' => $funcion->precio_base * $asientos->count(),
                    'estado' => 'pendiente',
                ]);

                foreach ($asientos as $asiento) {
                    ReservaAsiento::create([
                        'reserva_id' => $reserva->id,
                        'funcion_id' => $funcion->id,
                        'asiento_id' => $asiento->id,
                        'precio_unitario' => $funcion->precio_base,
                        'estado_bloqueo' => 'temporal',
                        'expira_en' => now()->addMinutes(10),
                    ]);
                }

                return $reserva;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Una o mas butacas ya fueron reservadas para esta funcion.',
                ], 409);
            }

            throw $e;
        }

        return response()->json(
            $reserva->load(['funcion.pelicula', 'reservaAsientos.asiento']),
            201
        );
    }

    public function show(Request $request, Reserva $reserva)
    {
        abort_unless($reserva->user_id === $request->user()->id, 403);

        return $reserva->load(['funcion.pelicula', 'reservaAsientos.asiento', 'pagos']);
    }
}
