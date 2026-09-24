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

    // Peliculas dadas de baja (activa = false), para la papelera del admin.
    public function inactivas()
    {
        return Pelicula::query()->with('genero')->where('activa', false)->orderBy('titulo')->get();
    }

    // Deshace un "dar de baja" hecho por error. Vuelve directo a cartelera
    // (destroy() le habia apagado en_cartelera, y sin esto quedaria activa
    // pero invisible tanto en cartelera como en proximamente).
    public function restaurar(Pelicula $pelicula)
    {
        $pelicula->update(['activa' => true, 'en_cartelera' => true]);

        return $pelicula->load('genero');
    }
}
