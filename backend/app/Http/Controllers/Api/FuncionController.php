<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funcion;
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
}
