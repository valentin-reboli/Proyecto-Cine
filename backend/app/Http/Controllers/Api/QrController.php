<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use OpenApi\Attributes as OA;

class QrController extends Controller
{
    #[OA\Get(
        path: '/api/entradas/qr/{codigo}.png',
        summary: 'Imagen del codigo QR de una entrada pagada',
        description: 'El codigo es el codigo_qr de la reserva. Lo usa n8n para adjuntar el QR al comprobante.',
        tags: ['Reservas'],
        parameters: [new OA\Parameter(name: 'codigo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Imagen PNG.'),
            new OA\Response(response: 404, description: 'No existe una entrada pagada con ese codigo.'),
        ]
    )]
    public function show(string $codigo)
    {
        abort_unless(Reserva::where('codigo_qr', $codigo)->where('estado', 'pagada')->exists(), 404);

        $opciones = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'quietzoneSize' => 2,
        ]);

        return response((new QRCode($opciones))->render($codigo), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
