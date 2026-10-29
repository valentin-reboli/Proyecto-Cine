<?php

namespace App\Http\Controllers;

use App\Models\Asiento;
use App\Models\Cliente;
use App\Models\Combo;
use App\Models\Funcion;
use App\Models\Reserva;
use App\Models\ReservaAsiento;
use App\Models\ReservaCombo;
use App\Services\DescuentoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        path: '/api/reservas/bloquear',
        summary: 'Bloquear butacas',
        description: 'Bloquea las butacas elegidas por 8 minutos para que nadie mas las pueda comprar mientras el cliente completa la compra. Devuelve un token que identifica el bloqueo. Si se manda un bloqueo anterior (bloqueo_id + bloqueo_token) se reemplazan sus butacas manteniendo el tiempo que le quedaba.',
        tags: ['Reservas'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['funcion_id', 'asientos'],
                properties: [
                    new OA\Property(property: 'funcion_id', type: 'integer', example: 1),
                    new OA\Property(property: 'asientos', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                    new OA\Property(property: 'bloqueo_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'bloqueo_token', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Butacas bloqueadas por 8 minutos.'),
            new OA\Response(response: 403, description: 'El token del bloqueo anterior no es valido.'),
            new OA\Response(response: 409, description: 'Una o mas butacas ya estan tomadas por otro cliente.'),
            new OA\Response(response: 410, description: 'El bloqueo anterior vencio.'),
            new OA\Response(response: 422, description: 'Datos invalidos, funcion cancelada o butacas de otra sala.'),
        ]
    )]
    public function bloquear(Request $request)
    {
        $data = $request->validate([
            'funcion_id' => ['required', 'integer', 'exists:funciones,id'],
            'asientos' => ['required', 'array', 'min:1', 'max:8'],
            'asientos.*' => ['integer', 'distinct', 'exists:asientos,id'],
            'bloqueo_id' => ['nullable', 'integer'],
            'bloqueo_token' => ['nullable', 'string', 'required_with:bloqueo_id'],
        ]);

        $funcion = Funcion::findOrFail($data['funcion_id']);
        $asientos = Asiento::whereIn('id', $data['asientos'])->get();

        if ($error = $this->validarButacas($funcion, $asientos)) {
            return $error;
        }

        ReservaAsiento::liberarVencidas($funcion->id);

        $anterior = null;
        if (! empty($data['bloqueo_id'])) {
            $anterior = Reserva::find($data['bloqueo_id']);

            if (! $anterior || ! $this->tokenValido($anterior, $data['bloqueo_token'])) {
                return response()->json(['message' => 'El bloqueo no es valido.'], 403);
            }

            if ($anterior->estado !== 'pendiente' || $anterior->funcion_id !== $funcion->id) {
                return response()->json([
                    'message' => 'Se termino el tiempo para completar la compra y las butacas se liberaron.',
                ], 410);
            }
        }

        try {
            $reserva = DB::transaction(function () use ($funcion, $asientos, $anterior) {
                if ($anterior) {
                    // Se cambian las butacas pero el reloj sigue corriendo desde el primer bloqueo.
                    $expira = ReservaAsiento::where('reserva_id', $anterior->id)
                        ->where('estado_bloqueo', 'temporal')
                        ->min('expira_en') ?? now()->addMinutes(Reserva::MINUTOS_BLOQUEO);

                    // Se borran (no se liberan) porque la misma reserva puede volver a tomar esas butacas.
                    ReservaAsiento::where('reserva_id', $anterior->id)
                        ->where('estado_bloqueo', 'temporal')
                        ->delete();

                    $reserva = $anterior;
                } else {
                    $expira = now()->addMinutes(Reserva::MINUTOS_BLOQUEO);

                    $reserva = Reserva::create([
                        'funcion_id' => $funcion->id,
                        'total' => $funcion->precio_base * $asientos->count(),
                        'estado' => 'pendiente',
                        'token_bloqueo' => Str::random(40),
                    ]);
                }

                foreach ($asientos as $asiento) {
                    ReservaAsiento::create([
                        'reserva_id' => $reserva->id,
                        'funcion_id' => $funcion->id,
                        'asiento_id' => $asiento->id,
                        'precio_unitario' => $funcion->precio_base,
                        'estado_bloqueo' => 'temporal',
                        'expira_en' => $expira,
                    ]);
                }

                $reserva->update(['total' => $funcion->precio_base * $asientos->count()]);

                return $reserva;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Una o mas butacas ya fueron tomadas por otro cliente. Elegí otras.',
            ], 409);
        }

        return response()->json($this->datosBloqueo($reserva), 201);
    }

    #[OA\Post(
        path: '/api/reservas/{reserva}/liberar',
        summary: 'Liberar un bloqueo de butacas',
        description: 'Libera al instante las butacas bloqueadas (por ejemplo si el cliente vuelve atras o se le termina el tiempo).',
        tags: ['Reservas'],
        parameters: [
            new OA\Parameter(name: 'reserva', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [new OA\Property(property: 'token', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Butacas liberadas.'),
            new OA\Response(response: 403, description: 'Token invalido.'),
            new OA\Response(response: 422, description: 'La reserva no esta pendiente.'),
        ]
    )]
    public function liberar(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        abort_unless($this->tokenValido($reserva, $data['token']), 403);

        if ($reserva->estado === 'expirada') {
            return response()->json(null, 204);
        }

        if ($reserva->estado !== 'pendiente') {
            return response()->json([
                'message' => 'La reserva no esta pendiente (estado actual: '.$reserva->estado.').',
            ], 422);
        }

        $this->liberarButacas($reserva);

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/reservas',
        summary: 'Crear reserva',
        description: 'Carga los datos del comprador. Si se manda un bloqueo (bloqueo_id + bloqueo_token, ver /api/reservas/bloquear) se completa esa reserva y el tiempo sigue corriendo; si no, bloquea las butacas por 8 minutos. Si no se paga en ese tiempo se liberan solas.',
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
                    new OA\Property(property: 'acepta_marketing', type: 'boolean', nullable: true, description: 'Si acepta recibir publicidad por correo/telefono.'),
                    new OA\Property(property: 'bloqueo_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'bloqueo_token', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reserva creada, butacas bloqueadas hasta que vence el tiempo.'),
            new OA\Response(response: 403, description: 'El token del bloqueo no es valido.'),
            new OA\Response(response: 409, description: 'Una o mas butacas ya fueron reservadas.'),
            new OA\Response(response: 410, description: 'El bloqueo vencio y las butacas se liberaron.'),
            new OA\Response(response: 422, description: 'Datos invalidos, funcion cancelada o butacas de otra sala.'),
        ]
    )]
    public function store(Request $request, DescuentoService $descuentos)
    {
        $data = $request->validate([
            'funcion_id' => ['required', 'integer', 'exists:funciones,id'],
            'asientos' => ['required', 'array', 'min:1'],
            'asientos.*' => ['integer', 'distinct', 'exists:asientos,id'],
            'invitado_nombre' => ['required', 'string', 'max:255'],
            'invitado_correo' => ['required', 'email', 'max:255'],
            'invitado_telefono' => ['nullable', 'string', 'max:30'],
            'acepta_marketing' => ['nullable', 'boolean'],
            'codigo_descuento' => ['nullable', 'string', 'max:20'],
            'combos' => ['nullable', 'array', 'max:20'],
            'combos.*.combo_id' => ['required', 'integer', 'distinct'],
            'combos.*.cantidad' => ['required', 'integer', 'min:1', 'max:20'],
            'bloqueo_id' => ['nullable', 'integer'],
            'bloqueo_token' => ['nullable', 'string', 'required_with:bloqueo_id'],
        ]);

        $funcion = Funcion::findOrFail($data['funcion_id']);
        $asientos = Asiento::whereIn('id', $data['asientos'])->get();

        if ($error = $this->validarButacas($funcion, $asientos)) {
            return $error;
        }

        ReservaAsiento::liberarVencidas($funcion->id);

        // Si las butacas ya estaban bloqueadas, se completa ese bloqueo.
        $bloqueo = null;
        if (! empty($data['bloqueo_id'])) {
            $bloqueo = Reserva::find($data['bloqueo_id']);

            if (! $bloqueo || ! $this->tokenValido($bloqueo, $data['bloqueo_token'])) {
                return response()->json(['message' => 'El bloqueo no es valido.'], 403);
            }

            if ($bloqueo->estado !== 'pendiente') {
                return response()->json([
                    'message' => 'Se termino el tiempo para completar la compra y las butacas se liberaron. Elegilas de nuevo.',
                ], 410);
            }

            $bloqueadas = ReservaAsiento::where('reserva_id', $bloqueo->id)
                ->where('estado_bloqueo', 'temporal')
                ->pluck('asiento_id')
                ->sort()->values()->all();

            if ($bloqueo->funcion_id !== $funcion->id || $bloqueadas !== $asientos->pluck('id')->sort()->values()->all()) {
                return response()->json(['message' => 'Las butacas no coinciden con las que tenes bloqueadas.'], 422);
            }
        }

        $descuento = null;
        if (! empty($data['codigo_descuento'])) {
            $descuento = $descuentos->validar($data['codigo_descuento'], $data['invitado_correo']);

            if (is_string($descuento)) {
                return response()->json(['message' => $descuento], 422);
            }
        }

        // Combos pedidos: solo los activos, al precio actual.
        $pedidos = collect($data['combos'] ?? []);
        $combos = Combo::whereIn('id', $pedidos->pluck('combo_id'))->where('activo', true)->get()->keyBy('id');

        if ($combos->count() !== $pedidos->count()) {
            return response()->json(['message' => 'Alguno de los combos elegidos ya no está disponible.'], 422);
        }

        $combosTotal = $pedidos->sum(fn ($p) => $combos[$p['combo_id']]->precio * $p['cantidad']);

        $subtotal = $funcion->precio_base * $asientos->count();
        $descuentoMonto = $descuento ? round($subtotal * $descuento->porcentaje / 100, 2) : 0;

        try {
            $reserva = DB::transaction(function () use ($data, $funcion, $asientos, $descuento, $descuentoMonto, $subtotal, $pedidos, $combos, $combosTotal, $bloqueo) {
                // Contacto para campañas: un registro por correo, siempre con los datos mas recientes.
                $cliente = Cliente::firstOrNew(['correo' => strtolower($data['invitado_correo'])]);
                $cliente->nombre = $data['invitado_nombre'];
                $cliente->telefono = $data['invitado_telefono'] ?? $cliente->telefono;
                if (array_key_exists('acepta_marketing', $data) && $data['acepta_marketing'] !== null) {
                    $cliente->acepta_marketing = (bool) $data['acepta_marketing'];
                }
                $cliente->save();

                $datosReserva = [
                    'funcion_id' => $funcion->id,
                    'invitado_nombre' => $data['invitado_nombre'],
                    'invitado_correo' => $data['invitado_correo'],
                    'invitado_telefono' => $data['invitado_telefono'] ?? null,
                    'total' => $subtotal - $descuentoMonto + $combosTotal,
                    'combos_total' => $combosTotal,
                    'descuento_id' => $descuento?->id,
                    'descuento_monto' => $descuentoMonto,
                    'estado' => 'pendiente',
                ];

                if ($bloqueo) {
                    // Puede llegar dos veces si fallo el pago: los combos se vuelven a cargar.
                    $bloqueo->update($datosReserva);
                    ReservaCombo::where('reserva_id', $bloqueo->id)->delete();
                    $reserva = $bloqueo;
                } else {
                    $reserva = Reserva::create($datosReserva);
                }

                foreach ($pedidos as $pedido) {
                    $combo = $combos[$pedido['combo_id']];
                    ReservaCombo::create([
                        'reserva_id' => $reserva->id,
                        'combo_id' => $combo->id,
                        'nombre' => $combo->nombre,
                        'precio_unitario' => $combo->precio,
                        'cantidad' => $pedido['cantidad'],
                    ]);
                }

                if (! $bloqueo) {
                    foreach ($asientos as $asiento) {
                        ReservaAsiento::create([
                            'reserva_id' => $reserva->id,
                            'funcion_id' => $funcion->id,
                            'asiento_id' => $asiento->id,
                            'precio_unitario' => $funcion->precio_base,
                            'estado_bloqueo' => 'temporal',
                            'expira_en' => now()->addMinutes(Reserva::MINUTOS_BLOQUEO),
                        ]);
                    }
                }

                return $reserva;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Una o mas butacas ya fueron reservadas para esta funcion.',
            ], 409);
        }

        return response()->json(
            $reserva->load(['funcion.pelicula', 'reservaAsientos.asiento', 'combos']),
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

        abort_unless($this->correoValido($reserva, $data['correo']), 403);

        return $reserva->load(['funcion.pelicula', 'reservaAsientos.asiento', 'pagos', 'descuentoGenerado', 'combos']);
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

        abort_unless($this->correoValido($reserva, $data['correo']), 403);

        if ($reserva->estado !== 'pendiente') {
            return response()->json([
                'message' => 'La reserva no esta pendiente (estado actual: '.$reserva->estado.').',
            ], 422);
        }

        $this->liberarButacas($reserva);

        return response()->json(null, 204);
    }

    private function validarButacas(Funcion $funcion, $asientos)
    {
        if ($funcion->cancelada) {
            return response()->json(['message' => 'La funcion fue cancelada.'], 422);
        }

        if ($asientos->contains(fn (Asiento $asiento) => $asiento->sala_id !== $funcion->sala_id)) {
            return response()->json([
                'message' => 'Hay butacas que no pertenecen a la sala de esta funcion.',
            ], 422);
        }

        if ($asientos->contains(fn (Asiento $asiento) => ! $asiento->activo)) {
            return response()->json(['message' => 'Hay butacas que no estan disponibles para la venta.'], 422);
        }

        return null;
    }

    private function liberarButacas(Reserva $reserva): void
    {
        // Con la fila bloqueada y volviendo a mirar el estado: si un pago entro
        // justo antes, la reserva ya esta pagada y no se toca.
        DB::transaction(function () use ($reserva) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->first();

            if ($reserva->estado !== 'pendiente') {
                return;
            }

            ReservaAsiento::where('reserva_id', $reserva->id)
                ->where('estado_bloqueo', 'temporal')
                ->update(['estado_bloqueo' => 'liberado']);

            $reserva->update(['estado' => 'expirada']);
        });
    }

    private function tokenValido(Reserva $reserva, ?string $token): bool
    {
        return $reserva->token_bloqueo && $token && hash_equals($reserva->token_bloqueo, $token);
    }

    private function correoValido(Reserva $reserva, string $correo): bool
    {
        return $reserva->invitado_correo && strcasecmp($reserva->invitado_correo, $correo) === 0;
    }

    // Lo que necesita el front para mostrar el contador: cuantos segundos quedan.
    private function datosBloqueo(Reserva $reserva): array
    {
        $butacas = ReservaAsiento::where('reserva_id', $reserva->id)
            ->where('estado_bloqueo', 'temporal')
            ->get(['asiento_id', 'expira_en']);

        $expira = $butacas->min('expira_en');

        return [
            'id' => $reserva->id,
            'token' => $reserva->token_bloqueo,
            'asientos' => $butacas->pluck('asiento_id')->values(),
            'expira_en' => $expira?->toIso8601String(),
            'segundos_restantes' => $expira ? max(0, (int) now()->diffInSeconds($expira, false)) : 0,
        ];
    }
}
