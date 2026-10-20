<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Sala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SalaController extends Controller
{
    #[OA\Get(
        path: '/api/salas',
        summary: 'Listar salas',
        tags: ['Salas'],
        responses: [
            new OA\Response(response: 200, description: 'Listado de salas.'),
        ]
    )]
    public function index()
    {
        return Sala::withCount('asientos')->orderBy('nombre')->get();
    }

    #[OA\Post(
        path: '/api/salas',
        summary: 'Crear sala',
        tags: ['Salas'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'filas', 'butacas_por_fila'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 100, example: 'Sala D'),
                    new OA\Property(property: 'filas', type: 'integer', minimum: 1, maximum: 26, example: 8),
                    new OA\Property(property: 'butacas_por_fila', type: 'integer', minimum: 1, maximum: 30, example: 12),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sala creada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Nombre invalido o repetido.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:salas,nombre'],
            'filas' => ['required', 'integer', 'min:1', 'max:26'],
            'butacas_por_fila' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        // La sala nace con sus butacas: filas A, B, C... con 1..N butacas cada una.
        $sala = DB::transaction(function () use ($data) {
            $sala = Sala::create(['nombre' => $data['nombre']]);

            foreach (range(0, $data['filas'] - 1) as $i) {
                foreach (range(1, $data['butacas_por_fila']) as $numero) {
                    $sala->asientos()->create(['fila' => chr(65 + $i), 'numero' => $numero]);
                }
            }

            return $sala;
        });

        return response()->json($sala->loadCount('asientos'), 201);
    }

    #[OA\Put(
        path: '/api/salas/{sala}',
        summary: 'Editar sala',
        tags: ['Salas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sala', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sala actualizada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Nombre invalido o repetido.'),
        ]
    )]
    public function update(Request $request, Sala $sala)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:salas,nombre,'.$sala->id],
        ]);

        $sala->update($data);

        return $sala;
    }

    #[OA\Delete(
        path: '/api/salas/{sala}',
        summary: 'Borrar sala',
        description: 'Rechaza el borrado si la sala tiene funciones asociadas.',
        tags: ['Salas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'sala', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Sala borrada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'La sala tiene funciones asociadas.'),
        ]
    )]
    public function destroy(Sala $sala)
    {
        if ($sala->funciones()->exists()) {
            return response()->json([
                'message' => 'No se puede borrar una sala que tiene funciones asociadas.',
            ], 422);
        }

        $sala->asientos()->delete();
        $sala->delete();

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/salas/{sala}/asientos',
        summary: 'Butacas de una sala (con su estado activo)',
        tags: ['Salas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'sala', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Butacas de la sala.')]
    )]
    public function asientos(Sala $sala)
    {
        return $sala->asientos()->orderBy('fila')->orderBy('numero')->get(['id', 'fila', 'numero', 'activo']);
    }

    #[OA\Put(
        path: '/api/asientos/{asiento}',
        summary: 'Habilitar o deshabilitar una butaca',
        description: 'activo=false la saca de la venta (por ejemplo, butaca rota).',
        tags: ['Salas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'asiento', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Butaca actualizada.')]
    )]
    public function actualizarAsiento(Request $request, Asiento $asiento)
    {
        $data = $request->validate(['activo' => ['required', 'boolean']]);

        $asiento->update($data);

        return $asiento;
    }
}
