<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'reserva_id',
        'mp_pago_id',
        'metodo',
        'estado',
        'detalle',
        'monto',
        'pagado_en',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    // Motivo para mostrarle al comprador (en la web y en el correo) de un pago rechazado o devuelto.
    // Para los rechazos, detalle es el status_detail de Mercado Pago.
    public function motivo(): string
    {
        if ($this->estado === 'reembolsado') {
            return in_array($this->detalle, ['bloqueo_vencido', 'reserva_expirada'], true)
                ? 'El pago llegó cuando ya se había terminado el tiempo y las butacas se liberaron. Te devolvimos el dinero.'
                : 'No pudimos completar la compra con ese pago. Te devolvimos el dinero.';
        }

        return match ($this->detalle) {
            'cc_rejected_insufficient_amount' => 'La tarjeta no tiene fondos suficientes.',
            'cc_rejected_bad_filled_security_code' => 'El código de seguridad es incorrecto.',
            'cc_rejected_bad_filled_date' => 'La fecha de vencimiento es incorrecta.',
            'cc_rejected_bad_filled_card_number', 'cc_rejected_bad_filled_other' => 'Revisá los datos de la tarjeta.',
            'cc_rejected_call_for_authorize' => 'Tenés que autorizar el pago con el banco de tu tarjeta.',
            'cc_rejected_card_disabled' => 'La tarjeta está deshabilitada. Llamá a tu banco para activarla.',
            'cc_rejected_duplicated_payment' => 'Ya hiciste un pago por ese monto. Si necesitás volver a pagar, usá otra tarjeta.',
            'cc_rejected_high_risk' => 'Mercado Pago rechazó el pago. Probá con otro medio de pago.',
            'cc_rejected_max_attempts' => 'Llegaste al límite de intentos con esa tarjeta. Probá con otra.',
            default => 'Mercado Pago rechazó el pago. Probá con otra tarjeta o medio de pago.',
        };
    }
}
