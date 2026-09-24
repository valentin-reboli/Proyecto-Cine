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

    // A partir de aca, gestion de admin (auth:sanctum, ver routes/api.php)
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $pelicula = Pelicula::create($data);

        return response()->json($pelicula->load('genero'), 201);
    }

    public function update(Request $request, Pelicula $pelicula)
    {
        $data = $this->validated($request, $pelicula);

        $pelicula->update($data);

        return $pelicula->load('genero');
    }

    public function destroy(Pelicula $pelicula)
    {
        // Soft delete: se da de baja del catalogo, no se borra el historico
        // de funciones/reservas que la referencian.
        $pelicula->update(['activa' => false, 'en_cartelera' => false]);

        return response()->json(null, 204);
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

    private function validated(Request $request, ?Pelicula $pelicula = null): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'sinopsis' => ['nullable', 'string'],
            'duracion_min' => ['required', 'integer', 'min:1'],
            'clasificacion' => ['nullable', 'string', 'max:10'],
            'poster_url' => ['nullable', 'string', 'max:255'],
            'backdrop_url' => ['nullable', 'string', 'max:255'],
            'trailer_url' => ['nullable', 'string', 'max:255'],
            'en_cartelera' => ['boolean'],
            'fecha_estreno' => ['nullable', 'date'],
            'genero_id' => ['required', 'integer', 'exists:generos,id'],
        ]);
    }
}
