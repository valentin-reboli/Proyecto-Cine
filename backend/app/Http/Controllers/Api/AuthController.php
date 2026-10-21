<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DosPasosService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        summary: 'Iniciar sesion (admin)',
        description: 'Autentica al admin por email/password y devuelve un token de Sanctum. Si el usuario tiene Google Authenticator activado, sin codigo responde requiere_codigo: true y hay que reenviar el pedido con el codigo de 6 digitos.',
        tags: ['Autenticacion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'codigo', type: 'string', example: '123456', description: 'Codigo de Google Authenticator (solo si el usuario lo tiene activado).'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso (user y token) o requiere_codigo: true si falta el codigo de Google Authenticator.'),
            new OA\Response(response: 422, description: 'Credenciales invalidas o codigo de Google Authenticator incorrecto.'),
        ]
    )]
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con ningún registro.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        return $this->responderLogin($request, $user);
    }

    #[OA\Post(
        path: '/api/login/google',
        summary: 'Iniciar sesion con Google (admin)',
        description: 'Verifica el ID token que devuelve Google Identity Services y, si el email coincide con un admin ya registrado, devuelve un token de Sanctum. No crea cuentas nuevas.',
        tags: ['Autenticacion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['credential'],
                properties: [
                    new OA\Property(property: 'credential', type: 'string', description: 'ID token (JWT) devuelto por el boton de Google.'),
                    new OA\Property(property: 'codigo', type: 'string', example: '123456', description: 'Codigo de Google Authenticator (solo si el usuario lo tiene activado).'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso (user y token) o requiere_codigo: true si falta el codigo de Google Authenticator.'),
            new OA\Response(response: 422, description: 'Token invalido, no verificado, sin cuenta de admin asociada o codigo de Google Authenticator incorrecto.'),
        ]
    )]
    public function loginWithGoogle(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        // Google valida la firma/expiracion del JWT y nos devuelve el payload
        // ya verificado. Evita sumar una dependencia nueva solo para validar
        // JWT a mano.
        try {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['credential'],
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'credential' => ['No se pudo verificar la cuenta con Google. Proba de nuevo en un rato.'],
            ]);
        }

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'credential' => ['El token de Google no es valido o expiro.'],
            ]);
        }

        $payload = $response->json();

        if (($payload['aud'] ?? null) !== config('services.google.client_id')) {
            throw ValidationException::withMessages([
                'credential' => ['El token de Google no corresponde a esta aplicacion.'],
            ]);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            throw ValidationException::withMessages([
                'credential' => ['El email de Google no esta verificado.'],
            ]);
        }

        $user = User::where('email', $payload['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'credential' => ['No existe una cuenta de administrador con ese email de Google.'],
            ]);
        }

        return $this->responderLogin($request, $user);
    }

    // Ultimo paso de los dos logins: si tiene Google Authenticator, pide y valida el codigo antes del token.
    private function responderLogin(Request $request, User $user)
    {
        if ($user->tieneDosPasos()) {
            $codigo = $request->input('codigo');

            if (blank($codigo)) {
                return response()->json(['requiere_codigo' => true]);
            }

            if (! app(DosPasosService::class)->verificar($user->dos_pasos_secreto, (string) $codigo)) {
                throw ValidationException::withMessages([
                    'codigo' => ['El codigo de Google Authenticator no es correcto.'],
                ]);
            }
        }

        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Cerrar sesion (admin)',
        description: 'Elimina el token de Sanctum utilizado actualmente.',
        tags: ['Autenticacion'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Sesion cerrada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado o token invalido.'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
