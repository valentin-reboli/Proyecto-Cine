<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formato extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    public function funciones()
    {
        return $this->hasMany(Funcion::class);
    }
}
