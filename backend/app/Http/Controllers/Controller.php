<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Cine Concordia API',
    description: 'API de Cine Concordia: catalogo de peliculas, funciones, reservas y pagos.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local de desarrollo'
)]
abstract class Controller
{
    //
}
