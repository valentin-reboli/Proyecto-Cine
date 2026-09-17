<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funcion extends Model
{
    protected $table = 'funciones';

    protected $fillable = [
        'pelicula_id',
        'sala_id',
        'formato_id',
        'fecha_hora',
        'idioma',
        'precio_base',
        'cancelada',
    ];

    public function pelicula()
    {
        return $this->belongsTo(Pelicula::class);
    }

    public function sala()
    {
        return $this->belongsTo(Sala::class);
    }

    public function formato()
    {
        return $this->belongsTo(Formato::class);
    }
}
