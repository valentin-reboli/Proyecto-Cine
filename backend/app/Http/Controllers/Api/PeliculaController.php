<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelicula;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PeliculaController extends Controller
{
    #[OA\Get(
        path: '/api/peliculas',
        summary: 'Listar cartelera o proximamente',
        description: 'Devuelve las peliculas activas. Por defecto la cartelera (en_cartelera=true); con ?proximamente=1 devuelve las de proximo estreno.',
        tags: ['Peliculas'],
        parameters: [
            new OA\Parameter(name: 'proximamente', in: 'query', required: false, description: 'Si es 1, devuelve proximos estrenos en vez de cartelera.', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de peliculas.'),
        ]
    )]
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

    #[OA\Get(
        path: '/api/peliculas/{pelicula}',
        summary: 'Detalle de una pelicula',
        description: 'Devuelve una pelicula con su genero y sus funciones no canceladas.',
        tags: ['Peliculas'],
        parameters: [
            new OA\Parameter(name: 'pelicula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pelicula encontrada.'),
            new OA\Response(response: 404, description: 'Pelicula no encontrada.'),
        ]
    )]
    public function show(Pelicula $pelicula)
    {
        return $pelicula->load(['genero', 'funciones' => function ($query) {
            $query->where('cancelada', false)->orderBy('fecha_hora');
        }, 'funciones.sala', 'funciones.formato']);
    }

    // A partir de aca, gestion de admin (auth:sanctum, ver routes/api.php)
    #[OA\Post(
        path: '/api/peliculas',
        summary: 'Crear pelicula',
        tags: ['Peliculas'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titulo', 'duracion_min', 'genero_id'],
                properties: [
                    new OA\Property(property: 'titulo', type: 'string', maxLength: 150, example: 'Duro de Matar'),
                    new OA\Property(property: 'sinopsis', type: 'string', nullable: true),
                    new OA\Property(property: 'duracion_min', type: 'integer', example: 132),
                    new OA\Property(property: 'clasificacion', type: 'string', maxLength: 10, nullable: true, example: '+16'),
                    new OA\Property(property: 'poster_url', type: 'string', nullable: true),
                    new OA\Property(property: 'backdrop_url', type: 'string', nullable: true),
                    new OA\Property(property: 'trailer_url', type: 'string', nullable: true),
                    new OA\Property(property: 'en_cartelera', type: 'boolean'),
                    new OA\Property(property: 'fecha_estreno', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'genero_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pelicula creada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos invalidos.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $pelicula = Pelicula::create($data);

        return response()->json($pelicula->load('genero'), 201);
    }

    #[OA\Put(
        path: '/api/peliculas/{pelicula}',
        summary: 'Editar pelicula',
        tags: ['Peliculas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'pelicula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pelicula actualizada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos invalidos.'),
        ]
    )]
    public function update(Request $request, Pelicula $pelicula)
    {
        $data = $this->validated($request, $pelicula);

        $pelicula->update($data);

        return $pelicula->load('genero');
    }

    #[OA\Delete(
        path: '/api/peliculas/{pelicula}',
        summary: 'Dar de baja una pelicula',
        description: 'Soft delete: la saca del catalogo pero no borra su historico de funciones/reservas.',
        tags: ['Peliculas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'pelicula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Pelicula dada de baja.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function destroy(Pelicula $pelicula)
    {
        // Soft delete: se da de baja del catalogo, no se borra el historico
        // de funciones/reservas que la referencian.
        $pelicula->update(['activa' => false, 'en_cartelera' => false]);

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/peliculas/inactivas',
        summary: 'Papelera de peliculas',
        description: 'Peliculas dadas de baja (activa=false).',
        tags: ['Peliculas'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado de peliculas inactivas.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function inactivas()
    {
        return Pelicula::query()->with('genero')->where('activa', false)->orderBy('titulo')->get();
    }

    #[OA\Post(
        path: '/api/peliculas/{pelicula}/restaurar',
        summary: 'Restaurar pelicula de la papelera',
        description: 'Deshace un dar de baja: vuelve a activa y a cartelera.',
        tags: ['Peliculas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'pelicula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pelicula restaurada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
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
