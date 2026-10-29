<?php

namespace App\Http\Controllers;

use App\Models\Descuento;
use App\Services\DescuentoService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DescuentoController extends Controller
{
    #[OA\Post(
        path: '/api/descuentos/validar',
        summary: 'Validar un codigo de descuento',
        description: 'El codigo solo sirve para el correo al que se regalo, una vez y hasta su vencimiento.',
        tags: ['Reservas'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo', 'correo'],
                properties: [
                    new OA\Property(property: 'codigo', type: 'string', example: 'CINE-AB12CD'),
                    new OA\Property(property: 'correo', type: 'string', format: 'email'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Codigo valido: devuelve el porcentaje.'),
            new OA\Response(response: 422, description: 'Codigo invalido, usado o vencido.'),
        ]
    )]
    public function validar(Request $request, DescuentoService $descuentos)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'correo' => ['required', 'email'],
        ]);

        $resultado = $descuentos->validar($data['codigo'], $data['correo']);

        if (! $resultado instanceof Descuento) {
            return response()->json(['message' => $resultado], 422);
        }

        return ['codigo' => $resultado->codigo, 'porcentaje' => $resultado->porcentaje];
    }
}
