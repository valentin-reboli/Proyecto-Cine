<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sala;
use Illuminate\Http\Request;

class SalaController extends Controller
{
    public function index()
    {
        return Sala::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:salas,nombre'],
        ]);

        return response()->json(Sala::create($data), 201);
    }

    public function update(Request $request, Sala $sala)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:salas,nombre,'.$sala->id],
        ]);

        $sala->update($data);

        return $sala;
    }

    public function destroy(Sala $sala)
    {
        if ($sala->funciones()->exists()) {
            return response()->json([
                'message' => 'No se puede borrar una sala que tiene funciones asociadas.',
            ], 422);
        }

        $sala->delete();

        return response()->json(null, 204);
    }
}
