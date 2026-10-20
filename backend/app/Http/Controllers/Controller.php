<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Cine Concordia API',
    description: 'API de Cine Concordia: catalogo de peliculas, funciones, reservas y pagos.'
)]
// Relativo: Swagger le pega al mismo servidor donde se abre (local o Railway).
#[OA\Server(
    url: '/',
    description: 'Este servidor'
)]
abstract class Controller
{
    //
}
