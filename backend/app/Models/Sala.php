<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sala extends Model
{
    protected $fillable = ['nombre'];

    public function asientos()
    {
        return $this->hasMany(Asiento::class);
    }

    public function funciones()
    {
        return $this->hasMany(Funcion::class);
    }
}
