<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FuncionController;
use App\Http\Controllers\Api\PeliculaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Catálogo público (cartelera, próximamente y funciones de cada película)
Route::get('/peliculas', [PeliculaController::class, 'index']);
Route::get('/peliculas/{pelicula}', [PeliculaController::class, 'show']);
Route::get('/funciones', [FuncionController::class, 'index']);
Route::get('/funciones/{funcion}', [FuncionController::class, 'show']);
