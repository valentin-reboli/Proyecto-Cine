<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sala;
use Illuminate\Http\Request;
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
        return Sala::orderBy('nombre')->get();
    }

    #[OA\Post(
        path: '/api/salas',
        summary: 'Crear sala',
        tags: ['Salas'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 100, example: 'Sala D'),
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
        ]);

        return response()->json(Sala::create($data), 201);
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

        $sala->delete();

        return response()->json(null, 204);
    }
}
