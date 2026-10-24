<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaniaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\DescuentoController;
use App\Http\Controllers\Api\EntradaController;
use App\Http\Controllers\Api\EstadoController;
use App\Http\Controllers\Api\FormatoController;
use App\Http\Controllers\Api\FuncionController;
use App\Http\Controllers\Api\GeneroController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\QrController;
use App\Http\Controllers\Api\PeliculaController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Api\SalaController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/estado', EstadoController::class);

// Limite de intentos: tambien frena que se adivine el codigo de Google Authenticator
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/login/google', [AuthController::class, 'loginWithGoogle'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Catálogo público (cartelera, próximamente y funciones de cada película)
Route::get('/peliculas', [PeliculaController::class, 'index']);
Route::get('/peliculas/inactivas', [PeliculaController::class, 'inactivas'])->middleware('auth:sanctum');
Route::get('/peliculas/{pelicula}', [PeliculaController::class, 'show']);
Route::get('/funciones', [FuncionController::class, 'index']);
Route::get('/funciones/{funcion}', [FuncionController::class, 'show']);
Route::get('/funciones/{funcion}/asientos', [FuncionController::class, 'asientos']);
Route::get('/generos', [GeneroController::class, 'index']);
Route::get('/salas', [SalaController::class, 'index']);
Route::get('/formatos', [FormatoController::class, 'index']);
Route::get('/combos', [ComboController::class, 'index']);
Route::get('/entradas/qr/{codigo}.png', [QrController::class, 'show']);

// Reservas: publicas, el cliente compra sin loguearse (se identifica por correo)
Route::get('/reservas', [ReservaController::class, 'index']);
Route::post('/descuentos/validar', [DescuentoController::class, 'validar'])->middleware('throttle:20,1');
Route::post('/reservas/bloquear', [ReservaController::class, 'bloquear'])->middleware('throttle:30,1');
Route::post('/reservas', [ReservaController::class, 'store']);
Route::get('/reservas/{reserva}', [ReservaController::class, 'show']);
Route::post('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
Route::post('/reservas/{reserva}/liberar', [ReservaController::class, 'liberar']);

// Pagos con Mercado Pago (el estado del pago se consulta siempre a Mercado Pago)
Route::get('/pagos/config', [PagoController::class, 'config']);
Route::post('/reservas/{reserva}/pago', [PagoController::class, 'store'])->middleware('throttle:10,1');
Route::post('/reservas/{reserva}/pago/preferencia', [PagoController::class, 'preferencia'])->middleware('throttle:10,1');
Route::post('/reservas/{reserva}/pago/verificar', [PagoController::class, 'verificar'])->middleware('throttle:20,1');
Route::post('/pagos/mercadopago/webhook', [PagoController::class, 'webhook']);

// Gestion de admin (requiere login)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/peliculas', [PeliculaController::class, 'store']);
    Route::put('/peliculas/{pelicula}', [PeliculaController::class, 'update']);
    Route::delete('/peliculas/{pelicula}', [PeliculaController::class, 'destroy']);
    Route::post('/peliculas/{pelicula}/restaurar', [PeliculaController::class, 'restaurar']);

    Route::get('/salas/{sala}/asientos', [SalaController::class, 'asientos']);
    Route::put('/asientos/{asiento}', [SalaController::class, 'actualizarAsiento']);
    Route::post('/salas', [SalaController::class, 'store']);
    Route::put('/salas/{sala}', [SalaController::class, 'update']);
    Route::delete('/salas/{sala}', [SalaController::class, 'destroy']);

    Route::post('/formatos', [FormatoController::class, 'store']);
    Route::put('/formatos/{formato}', [FormatoController::class, 'update']);
    Route::delete('/formatos/{formato}', [FormatoController::class, 'destroy']);

    Route::get('/admin/funciones', [FuncionController::class, 'admin']);
    Route::post('/funciones', [FuncionController::class, 'store']);
    Route::put('/funciones/{funcion}', [FuncionController::class, 'update']);
    Route::post('/funciones/{funcion}/cancelar', [FuncionController::class, 'cancelar']);

    Route::post('/generos', [GeneroController::class, 'store']);

    Route::get('/admin/reservas', [ReservaController::class, 'admin']);

    Route::post('/entradas/validar', [EntradaController::class, 'validar']);
    Route::get('/admin/combos', [ComboController::class, 'admin']);
    Route::post('/combos', [ComboController::class, 'store']);
    Route::put('/combos/{combo}', [ComboController::class, 'update']);
    Route::delete('/combos/{combo}', [ComboController::class, 'destroy']);

    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::get('/campanias', [CampaniaController::class, 'index']);
    Route::post('/campanias', [CampaniaController::class, 'store']);
    Route::put('/campanias/{campania}', [CampaniaController::class, 'update']);
    Route::delete('/campanias/{campania}', [CampaniaController::class, 'destroy']);
    Route::post('/campanias/{campania}/enviar', [CampaniaController::class, 'enviar']);

    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update']);
    Route::put('/usuarios/{usuario}/password', [UsuarioController::class, 'cambiarPassword']);
    Route::post('/usuarios/{usuario}/dos-pasos', [UsuarioController::class, 'iniciarDosPasos']);
    Route::post('/usuarios/{usuario}/dos-pasos/confirmar', [UsuarioController::class, 'confirmarDosPasos']);
    Route::delete('/usuarios/{usuario}/dos-pasos', [UsuarioController::class, 'desactivarDosPasos']);
});
