<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asiento extends Model
{
    use HasFactory;

    protected $fillable = ['sala_id', 'fila', 'numero', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
