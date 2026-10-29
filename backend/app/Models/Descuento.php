<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Descuento extends Model
{
    use HasFactory;

    protected $fillable = ['codigo', 'correo', 'porcentaje', 'vence_en', 'usado_en', 'reserva_origen_id'];

    protected $casts = [
        'vence_en' => 'datetime',
        'usado_en' => 'datetime',
    ];
}
