<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $fillable = [
        'user_id',
        'funcion_id',
        'total',
        'estado',
        'codigo_qr',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function funcion()
    {
        return $this->belongsTo(Funcion::class);
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
