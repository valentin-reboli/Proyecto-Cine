<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = [
        'reserva_id',
        'metodo',
        'estado',
        'comprobante',
        'monto',
        'pagado_en',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }
}
