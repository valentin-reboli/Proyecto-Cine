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
        'comprobante',
        'monto',
        'pagado_en',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }
}
