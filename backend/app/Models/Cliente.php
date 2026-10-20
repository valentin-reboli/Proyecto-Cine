<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = ['nombre', 'correo', 'telefono', 'acepta_marketing'];

    protected $casts = ['acepta_marketing' => 'boolean'];
}
