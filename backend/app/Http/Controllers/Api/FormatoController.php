<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formato;
use Illuminate\Http\Request;

class FormatoController extends Controller
{
    public function index()
    {
        return Formato::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:formatos,nombre'],
        ]);

        return response()->json(Formato::create($data), 201);
    }

    public function update(Request $request, Formato $formato)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:formatos,nombre,'.$formato->id],
        ]);

        $formato->update($data);

        return $formato;
    }

    public function destroy(Formato $formato)
    {
        if ($formato->funciones()->exists()) {
            return response()->json([
                'message' => 'No se puede borrar un formato que tiene funciones asociadas.',
            ], 422);
        }

        $formato->delete();

        return response()->json(null, 204);
    }
}
