<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formato;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FormatoController extends Controller
{
    #[OA\Get(
        path: '/api/formatos',
        summary: 'Listar formatos',
        tags: ['Formatos'],
        responses: [
            new OA\Response(response: 200, description: 'Listado de formatos.'),
        ]
    )]
    public function index()
    {
        return Formato::orderBy('nombre')->get();
    }

    #[OA\Post(
        path: '/api/formatos',
        summary: 'Crear formato',
        tags: ['Formatos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'IMAX'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Formato creado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Nombre invalido o repetido.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:formatos,nombre'],
        ]);

        return response()->json(Formato::create($data), 201);
    }

    #[OA\Put(
        path: '/api/formatos/{formato}',
        summary: 'Editar formato',
        tags: ['Formatos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'formato', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Formato actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Nombre invalido o repetido.'),
        ]
    )]
    public function update(Request $request, Formato $formato)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:formatos,nombre,'.$formato->id],
        ]);

        $formato->update($data);

        return $formato;
    }

    #[OA\Delete(
        path: '/api/formatos/{formato}',
        summary: 'Borrar formato',
        description: 'Rechaza el borrado si el formato tiene funciones asociadas.',
        tags: ['Formatos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'formato', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Formato borrado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'El formato tiene funciones asociadas.'),
        ]
    )]
    public function destroy(Formato $formato)
    {
        if ($formato->funciones()->exists()) {
            return response()->json([
                'message' => 'No se puede borrar un formato que tiene funciones asociadas.',
            ], 422);
        }

        $formato->delete();

        return response()->json(null, 204);
    }
}
