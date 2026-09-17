<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelicula;
use Illuminate\Http\Request;

class PeliculaController extends Controller
{
    public function index(Request $request)
    {
        $query = Pelicula::query()->with('genero')->where('activa', true);

        if ($request->boolean('proximamente')) {
            $query->where('en_cartelera', false)->whereNotNull('fecha_estreno');
        } else {
            $query->where('en_cartelera', true);
        }

        return $query->orderBy('titulo')->get();
    }

    public function show(Pelicula $pelicula)
    {
        return $pelicula->load(['genero', 'funciones' => function ($query) {
            $query->where('cancelada', false)->orderBy('fecha_hora');
        }, 'funciones.sala', 'funciones.formato']);
    }
}
