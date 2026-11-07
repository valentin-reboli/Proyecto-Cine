<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Avisos a n8n cuando la compra no se completa: pago pendiente, rechazado o devuelto.
class AvisosPagoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.n8n.compra_webhook' => 'https://n8n.test/compra',
            'services.n8n.token' => 'secreto-n8n',
        ]);
        Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);
    }

    private function reservar(): int
    {
        $sala = Sala::create(['nombre' => 'Sala Test']);
        $asiento = Asiento::create(['sala_id' => $sala->id, 'fila' => 'A', 'numero' => 1]);
        $funcion = Funcion::create([
            'pelicula_id' => Pelicula::create([
                'titulo' => 'Pelicula Test',
                'duracion_min' => 100,
                'en_cartelera' => true,
                'activa' => true,
                'genero_id' => Genero::create(['nombre' => 'Accion'])->id,
            ])->id,
            'sala_id' => $sala->id,
            'formato_id' => Formato::create(['nombre' => '2D'])->id,
            'fecha_hora' => now()->addDay(),
            'idioma' => 'DOB',
            'precio_base' => 1000,
        ]);

        return $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ])->assertCreated()->json('id');
    }

    private function eventos(): array
    {
        return Http::recorded()->map(fn ($par) => $par[0]['evento'])->all();
    }

    public function test_un_pago_rechazado_avisa_el_motivo_y_hasta_cuando_se_puede_reintentar(): void
    {
        $reservaId = $this->reservar();

        $this->pagar($reservaId, 'rejected', 'cc_rejected_insufficient_amount')->assertStatus(402);

        Http::assertSent(fn ($r) => $r['evento'] === 'pago_rechazado'
            && $r->hasHeader('X-Cine-Token', 'secreto-n8n')
            && $r['reserva']['id'] === $reservaId
            && $r['comprador']['correo'] === 'ana@test.com'
            && $r['butacas'] === ['A1']
            && $r['pago']['motivo'] === 'La tarjeta no tiene fondos suficientes.'
            && preg_match('/^\d{2}:\d{2}$/', $r['reintentar_hasta']));

        // Si después paga bien, llega la confirmación.
        $this->pagar($reservaId)->assertCreated();
        $this->assertSame(['pago_rechazado', 'compra_confirmada'], $this->eventos());
    }

    public function test_un_pago_pendiente_avisa_una_sola_vez_y_despues_la_confirmacion(): void
    {
        $reservaId = $this->reservar();

        $this->pagar($reservaId, 'in_process', 'pending_contingency')->assertStatus(202);
        // Mercado Pago repite la notificación con el pago todavía pendiente.
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();

        $this->mercadoPago->pagos['1000']['status'] = 'approved';
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();

        $this->assertSame(['pago_pendiente', 'compra_confirmada'], $this->eventos());
    }

    public function test_si_el_pago_llega_tarde_se_avisa_que_se_devolvio_el_dinero(): void
    {
        $reservaId = $this->reservar();
        $this->pagar($reservaId, 'in_process', 'pending_contingency')->assertStatus(202);

        $this->travel(9)->minutes();
        $this->mercadoPago->pagos['1000']['status'] = 'approved';
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();
        // Mercado Pago avisa del reembolso: no se manda otro correo.
        $this->postJson('/api/pagos/mercadopago/webhook?type=payment&data.id=1000')->assertOk();

        $this->assertSame(['pago_pendiente', 'compra_devuelta'], $this->eventos());
        Http::assertSent(fn ($r) => $r['evento'] === 'compra_devuelta'
            && str_contains($r['pago']['motivo'], 'Te devolvimos el dinero')
            && $r['reintentar_hasta'] === null);
    }
}
