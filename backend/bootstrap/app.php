<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En Railway el HTTPS lo termina su proxy; sin esto Laravel arma las
        // URLs (por ej. los assets de Swagger) con http y el navegador las bloquea.
        $middleware->trustProxies(at: '*');

        // La API no tiene pantalla de login a la cual redirigir: sin token valido es un 401.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los errores de /api siempre en JSON, aunque el cliente (por ej. Swagger) no mande Accept: application/json.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
