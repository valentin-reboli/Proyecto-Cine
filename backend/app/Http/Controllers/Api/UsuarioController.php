<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DosPasosService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
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
        return User::orderBy('name')->get()->map(fn (User $usuario) => $this->datos($usuario));
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

        return response()->json($this->datos($usuario), 201);
    }

    #[OA\Put(
        path: '/api/usuarios/{usuario}',
        summary: 'Editar nombre y email de un usuario del panel',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Naza'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'naza@cineconcordia.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Usuario inexistente.'),
            new OA\Response(response: 422, description: 'Datos invalidos o email repetido.'),
        ]
    )]
    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
        ]);

        $usuario->update($data);

        return $this->datos($usuario);
    }

    #[OA\Put(
        path: '/api/usuarios/{usuario}/password',
        summary: 'Cambiar la contraseña de un usuario del panel',
        description: 'Cierra las demas sesiones abiertas de ese usuario; la sesion actual sigue activa.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'nueva-clave'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'nueva-clave'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Contraseña actualizada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Usuario inexistente.'),
            new OA\Response(response: 422, description: 'Contraseña invalida o sin confirmar.'),
        ]
    )]
    public function cambiarPassword(Request $request, User $usuario)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $usuario->update(['password' => $data['password']]);

        $actual = $request->user()->currentAccessToken();

        $usuario->tokens()
            ->when($actual instanceof PersonalAccessToken, fn ($tokens) => $tokens->whereKeyNot($actual->getKey()))
            ->delete();

        return response()->noContent();
    }

    #[OA\Post(
        path: '/api/usuarios/{usuario}/dos-pasos',
        summary: 'Empezar a activar Google Authenticator para un usuario',
        description: 'Genera un secreto nuevo y devuelve el QR para escanear con la app. Queda pendiente hasta confirmarlo con un codigo.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Secreto, link otpauth y QR (data URI SVG).'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Usuario inexistente.'),
            new OA\Response(response: 422, description: 'El usuario ya tiene Google Authenticator activado.'),
        ]
    )]
    public function iniciarDosPasos(User $usuario, DosPasosService $dosPasos)
    {
        if ($usuario->tieneDosPasos()) {
            throw ValidationException::withMessages([
                'dos_pasos' => ['Este usuario ya tiene Google Authenticator activado. Desactivalo primero para generar uno nuevo.'],
            ]);
        }

        $secreto = $dosPasos->generarSecreto();
        $usuario->forceFill(['dos_pasos_secreto' => $secreto, 'dos_pasos_activado_en' => null])->save();

        $url = $dosPasos->urlParaApp($usuario, $secreto);

        return [
            'secreto' => $secreto,
            'otpauth_url' => $url,
            'qr' => $dosPasos->qr($url),
        ];
    }

    #[OA\Post(
        path: '/api/usuarios/{usuario}/dos-pasos/confirmar',
        summary: 'Confirmar Google Authenticator con el primer codigo',
        description: 'A partir de aca el login de ese usuario (email o Google) pide el codigo de 6 digitos.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo'],
                properties: [
                    new OA\Property(property: 'codigo', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Google Authenticator activado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Usuario inexistente.'),
            new OA\Response(response: 422, description: 'Codigo incorrecto o no se inicio la activacion.'),
        ]
    )]
    public function confirmarDosPasos(Request $request, User $usuario, DosPasosService $dosPasos)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string'],
        ]);

        if ($usuario->dos_pasos_secreto === null || $usuario->tieneDosPasos()) {
            throw ValidationException::withMessages([
                'codigo' => ['Primero generá el QR de Google Authenticator para este usuario.'],
            ]);
        }

        if (! $dosPasos->verificar($usuario->dos_pasos_secreto, $data['codigo'])) {
            throw ValidationException::withMessages([
                'codigo' => ['El codigo no es correcto. Revisá que sea el que muestra la app ahora.'],
            ]);
        }

        $usuario->forceFill(['dos_pasos_activado_en' => now()])->save();

        return $this->datos($usuario);
    }

    #[OA\Delete(
        path: '/api/usuarios/{usuario}/dos-pasos',
        summary: 'Desactivar Google Authenticator de un usuario',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Google Authenticator desactivado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Usuario inexistente.'),
        ]
    )]
    public function desactivarDosPasos(User $usuario)
    {
        $usuario->forceFill(['dos_pasos_secreto' => null, 'dos_pasos_activado_en' => null])->save();

        return $this->datos($usuario);
    }

    private function datos(User $usuario): array
    {
        return $usuario->only(['id', 'name', 'email', 'created_at']) + [
            'dos_pasos' => $usuario->tieneDosPasos(),
        ];
    }
}
