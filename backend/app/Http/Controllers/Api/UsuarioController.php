<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

// Usuarios del panel de admin (auth:sanctum, ver routes/api.php)
class UsuarioController extends Controller
{
    #[OA\Get(
        path: '/api/usuarios',
        summary: 'Listar usuarios del panel',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado de usuarios.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function index()
    {
        return User::orderBy('name')->get(['id', 'name', 'email', 'created_at']);
    }

    #[OA\Post(
        path: '/api/usuarios',
        summary: 'Crear usuario del panel',
        description: 'El usuario creado puede iniciar sesion en /api/login con su email y contraseña.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Jacobo'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jacobo@cineconcordia.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'secreto123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'secreto123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Datos invalidos o email repetido.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $usuario = User::create($data);

        return response()->json($usuario->only(['id', 'name', 'email', 'created_at']), 201);
    }
}
