<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campania extends Model
{
    protected $table = 'campanias';

    protected $fillable = [
        'nombre', 'canal', 'asunto', 'mensaje', 'programada_para',
        'estado', 'destinatarios', 'enviada_en', 'error',
    ];

    protected $casts = [
        'programada_para' => 'datetime',
        'enviada_en' => 'datetime',
    ];
}
