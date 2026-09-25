<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genero;
use Illuminate\Http\Request;

class GeneroController extends Controller
{
    public function index()
    {
        return Genero::orderBy('nombre')->get();
    }

    // A partir de aca, gestion de admin (auth:sanctum, ver routes/api.php)
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:generos,nombre'],
        ]);

        return response()->json(Genero::create($data), 201);
    }
}
