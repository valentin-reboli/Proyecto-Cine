<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genero;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GeneroController extends Controller
{
    #[OA\Get(
        path: '/api/generos',
        summary: 'Listar generos',
        tags: ['Generos'],
        responses: [
            new OA\Response(response: 200, description: 'Listado de generos.'),
        ]
    )]
    public function index()
    {
        return Genero::orderBy('nombre')->get();
    }

    // A partir de aca, gestion de admin (auth:sanctum, ver routes/api.php)
    #[OA\Post(
        path: '/api/generos',
        summary: 'Crear genero',
        tags: ['Generos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', maxLength: 50, example: 'Suspenso'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Genero creado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Nombre invalido o repetido.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:generos,nombre'],
        ]);

        return response()->json(Genero::create($data), 201);
    }
}
