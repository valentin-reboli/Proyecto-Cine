<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'categoria', 'descripcion', 'precio', 'imagen_url', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
