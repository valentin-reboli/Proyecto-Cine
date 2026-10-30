<?php

namespace App\Http\Controllers;

use App\Models\Genero;
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
}
