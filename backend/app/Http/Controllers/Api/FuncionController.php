<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Funcion;
use App\Models\ReservaAsiento;
use Illuminate\Http\Request;

class FuncionController extends Controller
{
    public function index(Request $request)
    {
        $query = Funcion::query()
            ->with(['pelicula', 'sala', 'formato'])
            ->where('cancelada', false);

        if ($request->filled('pelicula_id')) {
            $query->where('pelicula_id', $request->integer('pelicula_id'));
        }

        return $query->orderBy('fecha_hora')->get();
    }

    public function show(Funcion $funcion)
    {
        return $funcion->load(['pelicula', 'sala.asientos', 'formato']);
    }

    // Mapa de butacas de la funcion con su estado real (disponible/temporal/ocupado).
    // Una butaca "temporal" vencida (expira_en ya paso) cuenta como disponible,
    // aunque el cron reservas:liberar-vencidas todavia no haya pasado a liberarla.
    public function asientos(Funcion $funcion)
    {
        $estados = ReservaAsiento::where('funcion_id', $funcion->id)
            ->where(function ($query) {
                $query->where('estado_bloqueo', 'ocupado')
                    ->orWhere(function ($query) {
                        $query->where('estado_bloqueo', 'temporal')->where('expira_en', '>', now());
                    });
            })
            ->pluck('estado_bloqueo', 'asiento_id');

        return $funcion->sala->asientos()
            ->orderBy('fila')
            ->orderBy('numero')
            ->get()
            ->map(fn (Asiento $asiento) => [
                'id' => $asiento->id,
                'fila' => $asiento->fila,
                'numero' => $asiento->numero,
                'estado' => $estados->get($asiento->id, 'disponible'),
            ]);
    }

    // A partir de aca, gestion de admin (auth:sanctum, ver routes/api.php)
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $funcion = Funcion::create($data);

        return response()->json($funcion->load(['pelicula', 'sala', 'formato']), 201);
    }

    public function update(Request $request, Funcion $funcion)
    {
        $data = $this->validated($request);

        $funcion->update($data);

        return $funcion->load(['pelicula', 'sala', 'formato']);
    }

    // No se borra de verdad: hay reservas historicas que la referencian.
    // Se marca cancelada, que ya es el filtro que usan index()/show() del
    // catalogo publico para no mostrarla mas.
    public function cancelar(Funcion $funcion)
    {
        $funcion->update(['cancelada' => true]);

        return $funcion->load(['pelicula', 'sala', 'formato']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'pelicula_id' => ['required', 'integer', 'exists:peliculas,id'],
            'sala_id' => ['required', 'integer', 'exists:salas,id'],
            'formato_id' => ['required', 'integer', 'exists:formatos,id'],
            'fecha_hora' => ['required', 'date'],
            'idioma' => ['required', 'string', 'in:DOB,SUB'],
            'precio_base' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
