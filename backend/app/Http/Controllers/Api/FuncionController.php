<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Funcion;
use App\Models\ReservaAsiento;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FuncionController extends Controller
{
    #[OA\Get(
        path: '/api/funciones',
        summary: 'Listar funciones',
        description: 'Funciones no canceladas, opcionalmente filtradas por pelicula.',
        tags: ['Funciones'],
        parameters: [
            new OA\Parameter(name: 'pelicula_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de funciones.'),
        ]
    )]
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

    #[OA\Get(
        path: '/api/funciones/{funcion}',
        summary: 'Detalle de una funcion',
        tags: ['Funciones'],
        parameters: [
            new OA\Parameter(name: 'funcion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Funcion encontrada.'),
            new OA\Response(response: 404, description: 'Funcion no encontrada.'),
        ]
    )]
    public function show(Funcion $funcion)
    {
        return $funcion->load(['pelicula', 'sala.asientos', 'formato']);
    }

    // Mapa de butacas de la funcion con su estado real (disponible/temporal/ocupado).
    // Una butaca "temporal" vencida (expira_en ya paso) cuenta como disponible,
    // aunque el cron reservas:liberar-vencidas todavia no haya pasado a liberarla.
    #[OA\Get(
        path: '/api/funciones/{funcion}/asientos',
        summary: 'Mapa de butacas de una funcion',
        description: 'Devuelve todas las butacas de la sala con su estado real: disponible, temporal u ocupado.',
        tags: ['Funciones'],
        parameters: [
            new OA\Parameter(name: 'funcion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mapa de butacas.'),
            new OA\Response(response: 404, description: 'Funcion no encontrada.'),
        ]
    )]
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
    #[OA\Post(
        path: '/api/funciones',
        summary: 'Crear funcion',
        description: 'Crea un horario de proyeccion (pelicula + sala + formato + fecha/hora + precio).',
        tags: ['Funciones'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pelicula_id', 'sala_id', 'formato_id', 'fecha_hora', 'idioma', 'precio_base'],
                properties: [
                    new OA\Property(property: 'pelicula_id', type: 'integer', example: 1),
                    new OA\Property(property: 'sala_id', type: 'integer', example: 1),
                    new OA\Property(property: 'formato_id', type: 'integer', example: 1),
                    new OA\Property(property: 'fecha_hora', type: 'string', format: 'date-time', example: '2026-10-01 20:00:00'),
                    new OA\Property(property: 'idioma', type: 'string', enum: ['DOB', 'SUB'], example: 'SUB'),
                    new OA\Property(property: 'precio_base', type: 'number', example: 4200),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Funcion creada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos invalidos.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $funcion = Funcion::create($data);

        return response()->json($funcion->load(['pelicula', 'sala', 'formato']), 201);
    }

    #[OA\Put(
        path: '/api/funciones/{funcion}',
        summary: 'Editar funcion',
        tags: ['Funciones'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'funcion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Funcion actualizada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos invalidos.'),
        ]
    )]
    public function update(Request $request, Funcion $funcion)
    {
        $data = $this->validated($request);

        $funcion->update($data);

        return $funcion->load(['pelicula', 'sala', 'formato']);
    }

    // No se borra de verdad: hay reservas historicas que la referencian.
    // Se marca cancelada, que ya es el filtro que usan index()/show() del
    // catalogo publico para no mostrarla mas.
    #[OA\Post(
        path: '/api/funciones/{funcion}/cancelar',
        summary: 'Cancelar funcion',
        description: 'No la borra (hay reservas historicas que la referencian), la marca cancelada y desaparece del catalogo publico.',
        tags: ['Funciones'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'funcion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Funcion cancelada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
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
