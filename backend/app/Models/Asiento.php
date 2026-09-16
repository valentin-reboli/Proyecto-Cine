<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asiento extends Model
{
    protected $fillable = ['sala_id', 'fila', 'numero'];

    public function sala()
    {
        return $this->belongsTo(Sala::class);
    }
}
