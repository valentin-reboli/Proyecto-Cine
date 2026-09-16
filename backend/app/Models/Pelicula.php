<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pelicula extends Model
{
    protected $fillable = [
        'titulo',
        'sinopsis',
        'duracion_min',
        'clasificacion',
        'poster_url',
        'backdrop_url',
        'trailer_url',
        'en_cartelera',
        'fecha_estreno',
        'activa',
        'genero_id',
    ];

    public function genero()
    {
        return $this->belongsTo(Genero::class);
    }

    public function funciones()
    {
        return $this->hasMany(Funcion::class);
    }
}
