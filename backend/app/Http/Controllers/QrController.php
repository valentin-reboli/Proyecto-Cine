<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Services\ComprobanteService;
use App\Services\QrService;
use OpenApi\Attributes as OA;

// QR y comprobante en PDF de una entrada pagada. El codigo es el codigo_qr de la reserva
// (un UUID que solo conoce el comprador), por eso son publicos: los usa n8n para el correo.
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
    public function show(string $codigo, QrService $qr)
    {
        abort_unless(Reserva::where('codigo_qr', $codigo)->where('estado', 'pagada')->exists(), 404);

        return response($qr->png($codigo), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    #[OA\Get(
        path: '/api/entradas/comprobante/{codigo}.pdf',
        summary: 'Comprobante en PDF de una entrada pagada',
        description: 'El codigo es el codigo_qr de la reserva. Lo usa n8n para adjuntarlo al correo de confirmacion y el front para descargarlo.',
        tags: ['Reservas'],
        parameters: [new OA\Parameter(name: 'codigo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF.'),
            new OA\Response(response: 404, description: 'No existe una entrada pagada con ese codigo.'),
        ]
    )]
    public function comprobante(string $codigo, ComprobanteService $comprobantes)
    {
        $reserva = Reserva::where('codigo_qr', $codigo)->where('estado', 'pagada')->firstOrFail();

        return response($comprobantes->pdf($reserva), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$comprobantes->nombreArchivo($reserva).'"',
        ]);
    }
}
