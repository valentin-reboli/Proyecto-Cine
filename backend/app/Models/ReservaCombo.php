<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaCombo extends Model
{
    protected $fillable = ['reserva_id', 'combo_id', 'nombre', 'precio_unitario', 'cantidad'];
}
