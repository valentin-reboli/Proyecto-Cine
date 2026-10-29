<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Throwable;

// Diagnostico del deploy: se abre /api/estado en el navegador para ver que falla.
class EstadoController extends Controller
{
    #[OA\Get(
        path: '/api/estado',
        summary: 'Estado de la API y de la base de datos',
        tags: ['Estado'],
        responses: [
            new OA\Response(response: 200, description: 'API y base funcionando.'),
            new OA\Response(response: 503, description: 'La API anda pero la base no.'),
        ]
    )]
    public function __invoke()
    {
        $adminEmail = env('ADMIN_EMAIL');

        try {
            $migrada = Schema::hasTable('users');

            return response()->json([
                'api' => 'ok',
                'base_de_datos' => 'ok',
                'conexion' => DB::connection()->getDriverName(),
                'migraciones' => $migrada ? 'ok' : 'faltan',
                'usuarios' => $migrada ? User::count() : 0,
                'admin_email_cargado' => (bool) $adminEmail,
                'admin_existe' => $migrada && $adminEmail ? User::where('email', $adminEmail)->exists() : false,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'api' => 'ok',
                'base_de_datos' => 'error',
                'conexion' => config('database.default'),
                'detalle' => Str::limit($e->getMessage(), 300),
            ], 503);
        }
    }
}
