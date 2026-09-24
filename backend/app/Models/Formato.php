<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Formato extends Model
{
    protected $fillable = ['nombre'];

    public function funciones()
    {
        return $this->hasMany(Funcion::class);
    }
}
