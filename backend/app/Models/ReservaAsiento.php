<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaAsiento extends Model
{
    protected $fillable = [
        'reserva_id',
        'funcion_id',
        'asiento_id',
        'precio_unitario',
        'estado_bloqueo',
        'expira_en',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    public function funcion()
    {
        return $this->belongsTo(Funcion::class);
    }

    public function asiento()
    {
        return $this->belongsTo(Asiento::class);
    }
}
