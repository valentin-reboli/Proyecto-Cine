<?php

namespace App\Services;

use App\Models\Descuento;
use App\Models\Reserva;

class DescuentoService
{
    // Sin 0/O/1/I/L para que se pueda dictar o copiar sin confundirse.
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function generarPara(Reserva $reserva): Descuento
    {
        do {
            $codigo = 'CINE-'.collect(range(1, 6))->map(fn () => self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)])->implode('');
        } while (Descuento::where('codigo', $codigo)->exists());

        return Descuento::create([
            'codigo' => $codigo,
            'correo' => strtolower($reserva->invitado_correo),
            'porcentaje' => config('cine.descuento_porcentaje'),
            'vence_en' => now()->addDays(config('cine.descuento_vigencia_dias')),
            'reserva_origen_id' => $reserva->id,
        ]);
    }

    // Devuelve el descuento si se puede usar, o el motivo en texto si no.
    public function validar(string $codigo, string $correo): Descuento|string
    {
        $descuento = Descuento::where('codigo', strtoupper(trim($codigo)))->first();

        if (! $descuento || strcasecmp($descuento->correo, $correo) !== 0) {
            return 'El código no es válido para este correo.';
        }
        if ($descuento->usado_en) {
            return 'Ese código ya fue usado.';
        }
        if ($descuento->vence_en->isPast()) {
            return 'Ese código venció.';
        }

        return $descuento;
    }
}
