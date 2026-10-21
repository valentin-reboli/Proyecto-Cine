<?php

namespace App\Services;

use App\Models\User;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use PragmaRX\Google2FA\Google2FA;

// Verificacion en dos pasos con Google Authenticator (codigos TOTP de 6 digitos).
class DosPasosService
{
    public function __construct(private readonly Google2FA $google2fa = new Google2FA) {}

    public function generarSecreto(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    // Link otpauth:// que la app de Google Authenticator lee desde el QR.
    public function urlParaApp(User $user, string $secreto): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secreto);
    }

    // QR en SVG como data URI, listo para un <img src>.
    public function qr(string $url): string
    {
        $opciones = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
        ]);

        return (new QRCode($opciones))->render($url);
    }

    // Acepta el codigo actual y el de 30 segundos antes/despues (por si el reloj del celular corre distinto).
    public function verificar(string $secreto, ?string $codigo): bool
    {
        $codigo = preg_replace('/\s+/', '', (string) $codigo);

        if (! preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }

        return $this->google2fa->verifyKey($secreto, $codigo, 1);
    }
}
