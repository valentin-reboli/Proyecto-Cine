<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservaCombo extends Model
{
    use HasFactory;

    protected $fillable = ['reserva_id', 'combo_id', 'nombre', 'precio_unitario', 'cantidad'];
}
