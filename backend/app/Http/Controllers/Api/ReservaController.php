<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Funcion;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReservaController extends Controller
{
    // El cliente compra sin loguearse: se identifica por su correo.
    #[OA\Get(
        path: '/api/reservas',
        summary: 'Reservas de un cliente',
        description: 'Lista las reservas asociadas a un correo (el cliente no tiene cuenta, se identifica por correo).',
        tags: ['Reservas'],
        parameters: [
            new OA\Parameter(name: 'correo', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'email'), example: 'ana@x.com'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reservas del cliente.'),
            new OA\Response(response: 422, description: 'Correo faltante o invalido.'),
        ]
    )]
    public function index(Request $request)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
        ]);

        return Reserva::where('invitado_correo', $data['correo'])
            ->with(['funcion.pelicula', 'reservaAsientos.asiento'])
            ->latest()
            ->get();
    }

    #[OA\Post(
        path: '/api/reservas',
        summary: 'Crear reserva',
        description: 'Bloquea las butacas elegidas por 10 minutos (estado temporal). Si no se paga en ese tiempo se liberan solas.',
        tags: ['Reservas'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['funcion_id', 'asientos', 'invitado_nombre', 'invitado_correo'],
                properties: [
                    new OA\Property(property: 'funcion_id', type: 'integer', example: 1),
                    new OA\Property(property: 'asientos', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                    new OA\Property(property: 'invitado_nombre', type: 'string', maxLength: 255, example: 'Ana'),
                    new OA\Property(property: 'invitado_correo', type: 'string', format: 'email', example: 'ana@x.com'),
                    new OA\Property(property: 'invitado_telefono', type: 'string', maxLength: 30, nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reserva creada, butacas bloqueadas por 10 minutos.'),
            new OA\Response(response: 409, description: 'Una o mas butacas ya fueron reservadas.'),
            new OA\Response(response: 422, description: 'Datos invalidos, funcion cancelada o butacas de otra sala.'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'funcion_id' => ['required', 'integer', 'exists:funciones,id'],
            'asientos' => ['required', 'array', 'min:1'],
            'asientos.*' => ['integer', 'distinct', 'exists:asientos,id'],
            'invitado_nombre' => ['required', 'string', 'max:255'],
            'invitado_correo' => ['required', 'email', 'max:255'],
            'invitado_telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $funcion = Funcion::findOrFail($data['funcion_id']);

        if ($funcion->cancelada) {
            return response()->json(['message' => 'La funcion fue cancelada.'], 422);
        }

        $asientos = Asiento::whereIn('id', $data['asientos'])->get();

        if ($asientos->contains(fn (Asiento $asiento) => $asiento->sala_id !== $funcion->sala_id)) {
            return response()->json([
                'message' => 'Hay butacas que no pertenecen a la sala de esta funcion.',
            ], 422);
        }

        try {
            $reserva = DB::transaction(function () use ($data, $funcion, $asientos) {
                $reserva = Reserva::create([
                    'funcion_id' => $funcion->id,
                    'invitado_nombre' => $data['invitado_nombre'],
                    'invitado_correo' => $data['invitado_correo'],
                    'invitado_telefono' => $data['invitado_telefono'] ?? null,
                    'total' => $funcion->precio_base * $asientos->count(),
                    'estado' => 'pendiente',
                ]);

                foreach ($asientos as $asiento) {
                    ReservaAsiento::create([
                        'reserva_id' => $reserva->id,
                        'funcion_id' => $funcion->id,
                        'asiento_id' => $asiento->id,
                        'precio_unitario' => $funcion->precio_base,
                        'estado_bloqueo' => 'temporal',
                        'expira_en' => now()->addMinutes(10),
                    ]);
                }

                return $reserva;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Una o mas butacas ya fueron reservadas para esta funcion.',
                ], 409);
            }

            throw $e;
        }

        return response()->json(
            $reserva->load(['funcion.pelicula', 'reservaAsientos.asiento']),
            201
        );
    }

    // Listado completo para el admin (a diferencia de index(), no filtra por
    // correo). Filtro opcional por estado para separar pendientes/pagadas/etc.
    #[OA\Get(
        path: '/api/admin/reservas',
        summary: 'Listado de reservas (admin)',
        description: 'Todas las reservas, sin filtrar por correo. Filtro opcional por estado.',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pendiente', 'pagada', 'expirada'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de reservas.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function admin(Request $request)
    {
        $query = Reserva::query()->with(['funcion.pelicula', 'reservaAsientos.asiento']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        return $query->latest()->get();
    }

    #[OA\Get(
        path: '/api/reservas/{reserva}',
        summary: 'Detalle de una reserva',
        description: 'Solo accesible con el correo con el que se hizo la reserva.',
        tags: ['Reservas'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'correo', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'email'), example: 'ana@x.com'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reserva con funcion, butacas y pagos.'),
            new OA\Response(response: 403, description: 'El correo no coincide con el de la reserva.'),
            new OA\Response(response: 404, description: 'Reserva no encontrada.'),
        ]
    )]
    public function show(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
        ]);

        abort_unless(strcasecmp($reserva->invitado_correo, $data['correo']) === 0, 403);

        return $reserva->load(['funcion.pelicula', 'reservaAsientos.asiento', 'pagos']);
    }

    // Cancelacion manual: la dispara el timer del front cuando se vence el
    // tiempo para pagar, asi las butacas quedan libres al instante en vez de
    // esperar a que pase el cron reservas:liberar-vencidas.
    #[OA\Post(
        path: '/api/reservas/{reserva}/cancelar',
        summary: 'Cancelar una reserva pendiente',
        description: 'Libera las butacas al instante en vez de esperar a que venza. Solo con el correo de la reserva.',
        tags: ['Reservas'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo'],
                properties: [
                    new OA\Property(property: 'correo', type: 'string', format: 'email', example: 'ana@x.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Reserva cancelada, butacas liberadas.'),
            new OA\Response(response: 403, description: 'El correo no coincide con el de la reserva.'),
            new OA\Response(response: 422, description: 'La reserva no esta pendiente.'),
        ]
    )]
    public function cancelar(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
        ]);

        abort_unless(strcasecmp($reserva->invitado_correo, $data['correo']) === 0, 403);

        if ($reserva->estado !== 'pendiente') {
            return response()->json([
                'message' => 'La reserva no esta pendiente (estado actual: '.$reserva->estado.').',
            ], 422);
        }

        DB::transaction(function () use ($reserva) {
            ReservaAsiento::where('reserva_id', $reserva->id)
                ->where('estado_bloqueo', 'temporal')
                ->update(['estado_bloqueo' => 'liberado']);

            $reserva->update(['estado' => 'expirada']);
        });

        return response()->json(null, 204);
    }
}
