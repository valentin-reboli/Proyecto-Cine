<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Genera la imagen del codigo QR de una entrada. La usan el endpoint del QR y el comprobante en PDF.
class QrService
{
    public function png(string $codigo): string
    {
        $opciones = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($opciones))->render($codigo);
    }

    // Para incrustar el QR en el HTML del PDF (<img src="data:image/png;base64,...">).
    public function dataUri(string $codigo): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($codigo));
    }
}
