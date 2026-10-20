<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use OpenApi\Attributes as OA;

class ClienteController extends Controller
{
    #[OA\Get(
        path: '/api/clientes',
        summary: 'Listar contactos de compradores',
        description: 'Compradores con su correo, telefono y si aceptaron recibir publicidad.',
        tags: ['Campañas'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado de clientes.')]
    )]
    public function index()
    {
        return Cliente::orderByDesc('created_at')->get();
    }
}
