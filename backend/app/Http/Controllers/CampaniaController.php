<?php

namespace App\Http\Controllers;

use App\Models\Campania;
use App\Services\CampaniaSender;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CampaniaController extends Controller
{
    #[OA\Get(path: '/api/campanias', summary: 'Listar campañas', tags: ['Campañas'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado de campañas.')])]
    public function index()
    {
        return Campania::orderByDesc('created_at')->get();
    }

    #[OA\Post(path: '/api/campanias', summary: 'Crear campaña', description: 'Con programada_para queda programada y se envia sola; sin fecha queda en borrador.', tags: ['Campañas'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Campaña creada.')])]
    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(Campania::create($this->conEstado($data)), 201);
    }

    #[OA\Put(path: '/api/campanias/{campania}', summary: 'Editar campaña (solo si no fue enviada)', tags: ['Campañas'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Campaña actualizada.'), new OA\Response(response: 422, description: 'Ya fue enviada.')])]
    public function update(Request $request, Campania $campania)
    {
        if ($campania->estado === 'enviada') {
            return response()->json(['message' => 'La campaña ya fue enviada y no se puede editar.'], 422);
        }

        $campania->update($this->conEstado($this->validated($request)));

        return $campania;
    }

    #[OA\Delete(path: '/api/campanias/{campania}', summary: 'Eliminar campaña', tags: ['Campañas'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 204, description: 'Eliminada.')])]
    public function destroy(Campania $campania)
    {
        $campania->delete();

        return response()->noContent();
    }

    #[OA\Post(path: '/api/campanias/{campania}/enviar', summary: 'Enviar campaña ahora', tags: ['Campañas'], security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Resultado del envio (estado enviada o error).')])]
    public function enviar(Campania $campania, CampaniaSender $sender)
    {
        if ($campania->estado === 'enviada') {
            return response()->json(['message' => 'La campaña ya fue enviada.'], 422);
        }

        return $sender->enviar($campania);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'canal' => ['required', Rule::in(['email', 'whatsapp', 'telegram'])],
            'asunto' => ['nullable', 'string', 'max:200', 'required_if:canal,email'],
            'mensaje' => ['required', 'string', 'max:2000'],
            'programada_para' => ['nullable', 'date', 'after:now'],
        ]);
    }

    private function conEstado(array $data): array
    {
        $data['estado'] = ! empty($data['programada_para']) ? 'programada' : 'borrador';
        $data['error'] = null;

        return $data;
    }
}
