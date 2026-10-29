<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    // Tiempo que quedan bloqueadas las butacas para que el cliente complete la compra.
    public const MINUTOS_BLOQUEO = 8;

    protected $fillable = [
        'user_id',
        'funcion_id',
        'invitado_nombre',
        'invitado_correo',
        'invitado_telefono',
        'total',
        'estado',
        'token_bloqueo',
        'codigo_qr',
        'usada_en',
        'comprobante_enviado_en',
        'descuento_id',
        'descuento_monto',
        'combos_total',
    ];

    // El token solo se entrega una vez, al crear el bloqueo.
    protected $hidden = ['token_bloqueo'];

    protected $casts = [
        'usada_en' => 'datetime',
        'comprobante_enviado_en' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function funcion()
    {
        return $this->belongsTo(Funcion::class);
    }

    public function descuentoGenerado()
    {
        return $this->hasOne(Descuento::class, 'reserva_origen_id');
    }

    public function combos()
    {
        return $this->hasMany(ReservaCombo::class);
    }

    public function reservaAsientos()
    {
        return $this->hasMany(ReservaAsiento::class);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }
}
