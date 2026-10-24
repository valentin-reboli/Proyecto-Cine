<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Reserva;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoMercadoPagoTest extends TestCase
{
    use RefreshDatabase;

    private Funcion $funcion;

    protected function setUp(): void
    {
        parent::setUp();

        $sala = Sala::create(['nombre' => 'Sala Test']);
        foreach (range(1, 2) as $numero) {
            Asiento::create(['sala_id' => $sala->id, 'fila' => 'A', 'numero' => $numero]);
        }

        $this->funcion = Funcion::create([
            'pelicula_id' => Pelicula::create([
                'titulo' => 'Pelicula Test', 'duracion_min' => 100, 'en_cartelera' => true, 'activa' => true,
                'genero_id' => Genero::create(['nombre' => 'Accion'])->id,
            ])->id,
            'sala_id' => $sala->id,
            'formato_id' => Formato::create(['nombre' => '2D'])->id,
            'fecha_hora' => now()->addDay(),
            'idioma' => 'DOB',
            'precio_base' => 1000,
        ]);
    }

    private function reservar(): int
    {
        return $this->postJson('/api/reservas', [
            'funcion_id' => $this->funcion->id,
            'asientos' => [Asiento::first()->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ])->assertCreated()->json('id');
    }

    private function estadoButaca(): string
    {
        return collect($this->getJson("/api/funciones/{$this->funcion->id}/asientos")->json())
            ->firstWhere('id', Asiento::first()->id)['estado'];
    }

    public function test_sin_token_de_tarjeta_no_se_confirma_nada(): void
    {
        $reservaId = $this->reservar();

        // Lo que hacia antes el front: confirmar con cualquier dato.
        $this->postJson("/api/reservas/{$reservaId}/pago", ['correo' => 'ana@test.com', 'metodo' => 'tarjeta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'payment_method_id', 'installments']);

        $this->assertSame('pendiente', Reserva::find($reservaId)->estado);
        $this->assertSame('temporal', $this->estadoButaca());
    }

    public function test_pago_aprobado_ocupa_las_butacas_y_cobra_el_total_de_la_reserva(): void
    {
        $reservaId = $this->reservar();

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())
            ->assertCreated()
            ->assertJsonPath('estado', 'aprobado')
            ->assertJsonPath('reserva.estado', 'pagada');

        $this->assertSame('ocupado', $this->estadoButaca());
        $this->assertDatabaseHas('pagos', ['reserva_id' => $reservaId, 'estado' => 'aprobado', 'monto' => 1000, 'mp_pago_id' => '1000']);
    }

    public function test_pago_rechazado_deja_la_reserva_pendiente_para_reintentar(): void
    {
        $reservaId = $this->reservar();

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta(token: 'FUND'))
            ->assertStatus(402)
            ->assertJsonPath('message', 'La tarjeta no tiene fondos suficientes.');

        $this->assertSame('pendiente', Reserva::find($reservaId)->estado);
        $this->assertSame('temporal', $this->estadoButaca());

        // Reintenta con otra tarjeta mientras dura el bloqueo.
        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())->assertCreated();
        $this->assertSame('ocupado', $this->estadoButaca());
    }

    public function test_pago_pendiente_se_confirma_cuando_llega_el_webhook(): void
    {
        $reservaId = $this->reservar();

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta(token: 'CONT'))->assertStatus(202);
        $this->assertSame('pendiente', Reserva::find($reservaId)->estado);

        // Mercado Pago lo acredita y avisa por webhook (formato ?type=payment&data.id=...).
        $this->mercadoPago->pagos['1000']['status'] = 'approved';
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000', ['type' => 'payment', 'data' => ['id' => '1000']])
            ->assertOk();

        $this->assertSame('pagada', Reserva::find($reservaId)->estado);
        $this->assertSame('ocupado', $this->estadoButaca());
        $this->assertDatabaseCount('pagos', 1);

        // El webhook repetido no vuelve a procesar el pago.
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();
        $this->assertDatabaseCount('descuentos', 1);
    }

    public function test_si_el_pago_se_aprueba_despues_de_vencer_el_bloqueo_se_reembolsa(): void
    {
        $reservaId = $this->reservar();
        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta(token: 'CONT'))->assertStatus(202);

        $this->travel(9)->minutes();
        $this->mercadoPago->pagos['1000']['status'] = 'approved';
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();

        $this->assertSame('expirada', Reserva::find($reservaId)->estado);
        $this->assertSame('disponible', $this->estadoButaca());
        $this->assertSame(['1000'], $this->mercadoPago->reembolsos);
        $this->assertDatabaseHas('pagos', ['mp_pago_id' => '1000', 'estado' => 'reembolsado', 'detalle' => 'reserva_expirada']);
    }

    public function test_no_se_cobra_si_el_bloqueo_ya_vencio(): void
    {
        $reservaId = $this->reservar();
        $this->travel(9)->minutes();

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())->assertStatus(410);
        $this->assertSame([], $this->mercadoPago->pagos);
    }

    public function test_checkout_pro_crea_la_preferencia_y_verifica_el_pago_con_mercado_pago(): void
    {
        config(['services.mercadopago.url_retorno' => 'https://cine.test/reserva']);
        $reservaId = $this->reservar();

        $this->postJson("/api/reservas/{$reservaId}/pago/preferencia", ['correo' => 'otro@test.com'])->assertForbidden();
        $this->postJson("/api/reservas/{$reservaId}/pago/preferencia", ['correo' => 'ana@test.com'])
            ->assertCreated()
            ->assertJsonPath('id', "pref-{$reservaId}");
        $this->assertSame(1000.0, $this->mercadoPago->preferencias[0]['monto']);

        // Volvio sin pagar
        $this->postJson("/api/reservas/{$reservaId}/pago/verificar", ['correo' => 'ana@test.com'])->assertStatus(402);

        // Un pago de otra reserva no sirve (ni se toca)
        $ajeno = $this->mercadoPago->agregarPago($reservaId + 50, 1000);
        $this->postJson("/api/reservas/{$reservaId}/pago/verificar", ['correo' => 'ana@test.com', 'pago_id' => $ajeno['id']])
            ->assertUnprocessable();

        // Pago con la cuenta de Mercado Pago aprobado: se consulta y se confirma
        $pago = $this->mercadoPago->agregarPago($reservaId, 1000);
        $this->postJson("/api/reservas/{$reservaId}/pago/verificar", ['correo' => 'ana@test.com', 'pago_id' => $pago['id']])
            ->assertCreated()
            ->assertJsonPath('reserva.estado', 'pagada');

        // Si el webhook llega despues, no pasa nada nuevo
        $this->postJson("/api/pagos/mercadopago/webhook?type=payment&data.id={$pago['id']}")->assertOk();
        $this->assertDatabaseCount('pagos', 1);
        $this->assertSame([], $this->mercadoPago->reembolsos);
    }

    public function test_un_segundo_pago_aprobado_de_la_misma_reserva_se_reembolsa(): void
    {
        $reservaId = $this->reservar();
        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())->assertCreated();

        $duplicado = $this->mercadoPago->agregarPago($reservaId, 1000);
        $this->postJson("/api/pagos/mercadopago/webhook?type=payment&data.id={$duplicado['id']}")->assertOk();

        $this->assertSame([$duplicado['id']], $this->mercadoPago->reembolsos);
        $this->assertDatabaseHas('pagos', ['mp_pago_id' => $duplicado['id'], 'estado' => 'reembolsado']);
    }

    public function test_un_pago_por_un_monto_distinto_no_confirma_la_reserva(): void
    {
        $reservaId = $this->reservar();

        $pago = $this->mercadoPago->agregarPago($reservaId, 1);
        $this->postJson("/api/reservas/{$reservaId}/pago/verificar", ['correo' => 'ana@test.com', 'pago_id' => $pago['id']])
            ->assertStatus(410);

        $this->assertSame('pendiente', Reserva::find($reservaId)->estado);
        $this->assertSame([$pago['id']], $this->mercadoPago->reembolsos);
    }

    public function test_el_webhook_valida_la_firma_si_hay_clave_secreta(): void
    {
        config(['services.mercadopago.webhook_secret' => 'secreto']);
        $reservaId = $this->reservar();
        $pago = $this->mercadoPago->agregarPago($reservaId, 1000);
        $url = "/api/pagos/mercadopago/webhook?type=payment&data.id={$pago['id']}";

        $this->postJson($url, [], ['x-signature' => 'ts=1,v1=falsa', 'x-request-id' => 'req-1'])->assertUnauthorized();
        $this->assertSame('pendiente', Reserva::find($reservaId)->estado);

        $firma = hash_hmac('sha256', "id:{$pago['id']};request-id:req-1;ts:1;", 'secreto');
        $this->postJson($url, [], ['x-signature' => "ts=1,v1={$firma}", 'x-request-id' => 'req-1'])->assertOk();
        $this->assertSame('pagada', Reserva::find($reservaId)->estado);
    }

    public function test_config_expone_solo_la_clave_publica(): void
    {
        config(['services.mercadopago.public_key' => 'TEST-publica', 'services.mercadopago.access_token' => 'TEST-secreto']);

        $respuesta = $this->getJson('/api/pagos/config')->assertOk()->assertJsonPath('public_key', 'TEST-publica');
        $this->assertStringNotContainsString('TEST-secreto', $respuesta->getContent());
    }

    public function test_si_mercado_pago_falla_en_modo_prueba_se_muestra_el_motivo(): void
    {
        $reservaId = $this->reservar();

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta(token: 'ERROR'))
            ->assertStatus(503)
            ->assertJsonPath('message', 'No pudimos procesar el pago con Mercado Pago. Revisá los datos e intentá de nuevo. (Mercado Pago respondio 400: 2034 Invalid users involved)');

        config(['services.mercadopago.modo_prueba' => false]);
        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta(token: 'ERROR'))
            ->assertStatus(503)
            ->assertJsonPath('message', 'No pudimos procesar el pago con Mercado Pago. Revisá los datos e intentá de nuevo.');

        $this->assertSame('pendiente', Reserva::find($reservaId)->estado);
    }
}
