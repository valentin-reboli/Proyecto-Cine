<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ReservaCombo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ComboController extends Controller
{
    #[OA\Get(
        path: '/api/combos',
        summary: 'Listar combos disponibles',
        description: 'Combos, pochoclos, bebidas y golosinas activos, para agregar a la compra.',
        tags: ['Combos'],
        responses: [new OA\Response(response: 200, description: 'Listado de combos activos.')]
    )]
    public function index()
    {
        return Combo::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get();
    }

    #[OA\Get(path: '/api/admin/combos', summary: 'Listar todos los combos (admin)', tags: ['Combos'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado completo, activos o no.')])]
    public function admin()
    {
        return Combo::orderByDesc('activo')->orderBy('categoria')->orderBy('nombre')->get();
    }

    #[OA\Post(path: '/api/combos', summary: 'Crear combo', tags: ['Combos'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Combo creado.')])]
    public function store(Request $request)
    {
        return response()->json(Combo::create($this->validated($request)), 201);
    }

    #[OA\Put(path: '/api/combos/{combo}', summary: 'Editar combo', tags: ['Combos'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Combo actualizado.')])]
    public function update(Request $request, Combo $combo)
    {
        $combo->update($this->validated($request));

        return $combo;
    }

    #[OA\Delete(path: '/api/combos/{combo}', summary: 'Borrar combo', description: 'Si ya se vendio, no se borra: hay que desactivarlo.', tags: ['Combos'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 204, description: 'Borrado.'), new OA\Response(response: 422, description: 'Ya se vendio.')])]
    public function destroy(Combo $combo)
    {
        if (ReservaCombo::where('combo_id', $combo->id)->exists()) {
            return response()->json(['message' => 'Este combo ya se vendió: desactivalo en vez de borrarlo.'], 422);
        }

        $combo->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'categoria' => ['required', Rule::in(['combo', 'pochoclo', 'bebida', 'golosina'])],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'imagen_url' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);
    }
}
