<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genero;

class GeneroController extends Controller
{
    public function index()
    {
        return Genero::orderBy('nombre')->get();
    }
}
