<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Combo;
use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Sala;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservaFlowTest extends TestCase
{
    use RefreshDatabase;

    private function crearFuncion(): Funcion
    {
        $genero = Genero::create(['nombre' => 'Accion']);
        $sala = Sala::create(['nombre' => 'Sala Test']);
        $formato = Formato::create(['nombre' => '2D']);

        foreach (range(1, 3) as $numero) {
            Asiento::create(['sala_id' => $sala->id, 'fila' => 'A', 'numero' => $numero]);
        }

        $pelicula = Pelicula::create([
            'titulo' => 'Pelicula Test',
            'duracion_min' => 100,
            'en_cartelera' => true,
            'activa' => true,
            'genero_id' => $genero->id,
        ]);

        return Funcion::create([
            'pelicula_id' => $pelicula->id,
            'sala_id' => $sala->id,
            'formato_id' => $formato->id,
            'fecha_hora' => now()->addDay(),
            'idioma' => 'DOB',
            'precio_base' => 1000,
        ]);
    }

    public function test_la_reserva_guarda_el_contacto_para_campanias(): void
    {
        $funcion = $this->crearFuncion();
        $asientos = Asiento::where('sala_id', $funcion->sala_id)->get();

        $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asientos[0]->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'Ana@Test.com',
            'invitado_telefono' => '3456123456',
            'acepta_marketing' => true,
        ])->assertCreated();

        $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asientos[1]->id],
            'invitado_nombre' => 'Ana G',
            'invitado_correo' => 'ana@test.com',
        ])->assertCreated();

        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', [
            'correo' => 'ana@test.com',
            'nombre' => 'Ana G',
            'telefono' => '3456123456',
            'acepta_marketing' => true,
        ]);
    }

    public function test_flujo_completo_de_reserva_y_pago(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $reservaResponse = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ]);

        $reservaResponse->assertCreated();
        $reservaId = $reservaResponse->json('id');

        $mapa = $this->getJson("/api/funciones/{$funcion->id}/asientos");
        $mapa->assertOk();
        $this->assertSame('temporal', collect($mapa->json())->firstWhere('id', $asiento->id)['estado']);

        $pagoResponse = $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta());

        $pagoResponse->assertCreated();

        $mapaFinal = $this->getJson("/api/funciones/{$funcion->id}/asientos");
        $this->assertSame('ocupado', collect($mapaFinal->json())->firstWhere('id', $asiento->id)['estado']);
    }

    public function test_no_se_puede_reservar_dos_veces_la_misma_butaca(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $primera = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ]);
        $primera->assertCreated();

        $segunda = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Beto',
            'invitado_correo' => 'beto@test.com',
        ]);

        $segunda->assertStatus(409);
    }

    public function test_cancelar_libera_la_butaca_al_instante(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $reserva = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ])->json();

        $this->postJson("/api/reservas/{$reserva['id']}/cancelar", [
            'correo' => 'ana@test.com',
        ])->assertNoContent();

        $mapa = $this->getJson("/api/funciones/{$funcion->id}/asientos");
        $this->assertSame('disponible', collect($mapa->json())->firstWhere('id', $asiento->id)['estado']);

        $otra = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Beto',
            'invitado_correo' => 'beto@test.com',
        ]);
        $otra->assertCreated();
    }

    public function test_crear_sala_genera_butacas_y_una_butaca_bloqueada_no_se_vende(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $sala = $this->postJson('/api/salas', ['nombre' => 'Sala Nueva', 'filas' => 3, 'butacas_por_fila' => 4])
            ->assertCreated()->assertJsonPath('asientos_count', 12)->json();

        $asiento = \App\Models\Asiento::where('sala_id', $sala['id'])->first();
        $this->putJson("/api/asientos/{$asiento->id}", ['activo' => false])->assertOk();

        $funcion = $this->crearFuncion();
        $funcion->update(['sala_id' => $sala['id']]);

        $this->getJson("/api/funciones/{$funcion->id}/asientos")
            ->assertJsonFragment(['id' => $asiento->id, 'estado' => 'bloqueado']);

        $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ])->assertUnprocessable();
    }

    public function test_al_pagar_se_envia_el_comprobante_con_el_qr_a_n8n(): void
    {
        config(['services.n8n.compra_webhook' => 'https://n8n.test/compra']);
        Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);

        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $reservaId = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
            'invitado_telefono' => '3456123456',
        ])->json('id');

        $pago = $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())
            ->assertCreated();

        $codigo = $pago->json('reserva.codigo_qr');
        $this->assertNotNull($pago->json('reserva.comprobante_enviado_en'));

        Http::assertSent(fn ($r) => $r['evento'] === 'compra_confirmada'
            && $r['comprador']['correo'] === 'ana@test.com'
            && $r['butacas'] === ['A1']
            && str_ends_with($r['qr_url'], "/api/entradas/qr/{$codigo}.png"));

        $this->get("/api/entradas/qr/{$codigo}.png")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/api/entradas/qr/inexistente.png')->assertNotFound();
    }

    public function test_si_n8n_falla_la_compra_igual_se_confirma(): void
    {
        config(['services.n8n.compra_webhook' => 'https://n8n.test/compra']);
        Http::fake(['n8n.test/*' => Http::response('error', 500)]);

        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();
        $reservaId = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ])->json('id');

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())
            ->assertCreated()
            ->assertJsonPath('reserva.estado', 'pagada')
            ->assertJsonPath('reserva.comprobante_enviado_en', null);
    }

    public function test_cada_compra_regala_un_descuento_que_se_canjea_una_sola_vez_y_solo_con_ese_correo(): void
    {
        $funcion = $this->crearFuncion();
        $asientos = Asiento::where('sala_id', $funcion->sala_id)->get();

        $r1 = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asientos[0]->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
        ])->json('id');
        $this->postJson("/api/reservas/{$r1}/pago", $this->tarjeta())->assertCreated();

        $regalo = $this->getJson("/api/reservas/{$r1}?correo=ana@test.com")->json('descuento_generado');
        $this->assertSame(10, $regalo['porcentaje']);

        // Otro correo no puede usarlo
        $this->postJson('/api/descuentos/validar', ['codigo' => $regalo['codigo'], 'correo' => 'otro@test.com'])->assertUnprocessable();
        $this->postJson('/api/descuentos/validar', ['codigo' => strtolower($regalo['codigo']), 'correo' => 'ana@test.com'])
            ->assertOk()->assertJsonPath('porcentaje', 10);

        // La reserva con descuento cobra 10% menos (1000 -> 900)
        $conDescuento = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asientos[1]->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
            'codigo_descuento' => $regalo['codigo'],
        ])->assertCreated();
        $this->assertEquals(900, $conDescuento->json('total'));
        $r2 = $conDescuento->json('id');

        // Una segunda reserva con el mismo codigo se arma, pero solo una puede pagarse
        $r3 = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asientos[2]->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
            'codigo_descuento' => $regalo['codigo'],
        ])->assertCreated()->json('id');

        $this->postJson("/api/reservas/{$r2}/pago", $this->tarjeta())->assertCreated();
        $this->postJson("/api/reservas/{$r3}/pago", $this->tarjeta())->assertUnprocessable();

        $this->postJson('/api/descuentos/validar', ['codigo' => $regalo['codigo'], 'correo' => 'ana@test.com'])
            ->assertUnprocessable()->assertJsonPath('message', 'Ese código ya fue usado.');
    }

    public function test_combos_se_suman_al_total_y_solo_se_ofrecen_los_activos(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $combo = $this->postJson('/api/combos', [
            'nombre' => 'Combo Pochoclo + Gaseosa', 'categoria' => 'combo', 'precio' => 500, 'activo' => true,
        ])->assertCreated()->json();
        $inactivo = Combo::create(['nombre' => 'Viejo', 'categoria' => 'bebida', 'precio' => 100, 'activo' => false]);

        $this->assertCount(1, $this->getJson('/api/combos')->json());
        $this->assertCount(2, $this->getJson('/api/admin/combos')->json());

        $funcion = $this->crearFuncion();
        $asientos = Asiento::where('sala_id', $funcion->sala_id)->get();
        $base = [
            'funcion_id' => $funcion->id, 'asientos' => [$asientos[0]->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
        ];

        // combo desactivado: se rechaza
        $this->postJson('/api/reservas', $base + ['combos' => [['combo_id' => $inactivo->id, 'cantidad' => 1]]])->assertUnprocessable();

        // 1 entrada (1000) + 2 combos (2 x 500)
        $reserva = $this->postJson('/api/reservas', $base + ['combos' => [['combo_id' => $combo['id'], 'cantidad' => 2]]])
            ->assertCreated();
        $this->assertEquals(2000, $reserva->json('total'));
        $this->assertEquals(1000, $reserva->json('combos_total'));
        $this->assertSame('Combo Pochoclo + Gaseosa', $reserva->json('combos.0.nombre'));

        // un combo vendido no se puede borrar, solo desactivar
        $this->deleteJson("/api/combos/{$combo['id']}")->assertUnprocessable();
        $this->deleteJson("/api/combos/{$inactivo->id}")->assertNoContent();
    }

    private function estadoDe(Funcion $funcion, Asiento $asiento): string
    {
        return collect($this->getJson("/api/funciones/{$funcion->id}/asientos")->json())
            ->firstWhere('id', $asiento->id)['estado'];
    }

    public function test_bloquear_butacas_las_reserva_8_minutos_y_nadie_mas_las_puede_tomar(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $bloqueo = $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
        ])->assertCreated()->json();

        $this->assertNotEmpty($bloqueo['token']);
        $this->assertSame([$asiento->id], $bloqueo['asientos']);
        $this->assertEqualsWithDelta(8 * 60, $bloqueo['segundos_restantes'], 2);
        $this->assertSame('temporal', $this->estadoDe($funcion, $asiento));

        // Otro cliente no la puede bloquear ni comprar
        $this->postJson('/api/reservas/bloquear', ['funcion_id' => $funcion->id, 'asientos' => [$asiento->id]])
            ->assertStatus(409);
        $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
            'invitado_nombre' => 'Beto', 'invitado_correo' => 'beto@test.com',
        ])->assertStatus(409);

        // El token no se expone en otros listados
        $this->assertArrayNotHasKey('token_bloqueo', $this->getJson("/api/reservas?correo=ana@test.com")->json()[0] ?? []);

        // Quien la bloqueo completa sus datos y paga: queda ocupada
        $reserva = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => $bloqueo['token'],
        ])->assertCreated();
        $this->assertSame($bloqueo['id'], $reserva->json('id'));
        $this->assertArrayNotHasKey('token_bloqueo', $reserva->json());

        $this->postJson("/api/reservas/{$bloqueo['id']}/pago", $this->tarjeta())
            ->assertCreated();

        // Comprada: sigue ocupada aunque pasen los 8 minutos
        $this->travel(30)->minutes();
        $this->artisan('reservas:liberar-vencidas')->assertSuccessful();
        $this->assertSame('ocupado', $this->estadoDe($funcion, $asiento));
    }

    public function test_si_pasan_los_8_minutos_sin_comprar_las_butacas_vuelven_a_estar_disponibles(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $bloqueo = $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
        ])->assertCreated()->json();

        $this->travel(7)->minutes();
        $this->assertSame('temporal', $this->estadoDe($funcion, $asiento));

        $this->travel(2)->minutes();
        $this->assertSame('disponible', $this->estadoDe($funcion, $asiento));

        // Aunque el cron todavia no haya pasado, otro cliente ya la puede tomar
        $this->postJson('/api/reservas/bloquear', ['funcion_id' => $funcion->id, 'asientos' => [$asiento->id]])
            ->assertCreated();

        // Y el primero ya no puede completar la compra
        $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => $bloqueo['token'],
        ])->assertStatus(410);
    }

    public function test_pagar_una_reserva_vencida_devuelve_410_aunque_no_haya_pasado_el_cron(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $reservaId = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
        ])->assertCreated()->json('id');

        $this->travel(9)->minutes();

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())
            ->assertStatus(410);
        $this->assertSame('disponible', $this->estadoDe($funcion, $asiento));
    }

    public function test_pagar_dos_veces_la_misma_reserva_cobra_una_sola_vez(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $reservaId = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
        ])->assertCreated()->json('id');

        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())->assertCreated();
        $this->postJson("/api/reservas/{$reservaId}/pago", $this->tarjeta())->assertUnprocessable();

        $this->assertDatabaseCount('pagos', 1);
        $this->assertSame('ocupado', $this->estadoDe($funcion, $asiento));
    }

    public function test_cambiar_las_butacas_bloqueadas_mantiene_el_tiempo_que_quedaba(): void
    {
        $funcion = $this->crearFuncion();
        [$a1, $a2] = Asiento::where('sala_id', $funcion->sala_id)->get();

        $bloqueo = $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$a1->id],
        ])->assertCreated()->json();

        $this->travel(3)->minutes();

        $cambio = $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$a1->id, $a2->id],
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => $bloqueo['token'],
        ])->assertCreated()->json();

        $this->assertSame($bloqueo['id'], $cambio['id']);
        $this->assertEqualsWithDelta(5 * 60, $cambio['segundos_restantes'], 2);

        // Se saca la A1 y se vuelve a poner: la misma reserva la puede retomar
        $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$a2->id],
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => $bloqueo['token'],
        ])->assertCreated();
        $this->assertSame('disponible', $this->estadoDe($funcion, $a1));

        $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$a1->id, $a2->id],
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => $bloqueo['token'],
        ])->assertCreated();

        // Con un token equivocado no se puede tocar el bloqueo
        $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$a1->id],
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => 'otro',
        ])->assertForbidden();
        $this->postJson("/api/reservas/{$bloqueo['id']}/liberar", ['token' => 'otro'])->assertForbidden();
    }

    public function test_liberar_un_bloqueo_deja_las_butacas_disponibles_al_instante(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $bloqueo = $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
        ])->json();

        $this->postJson("/api/reservas/{$bloqueo['id']}/liberar", ['token' => $bloqueo['token']])->assertNoContent();
        $this->assertSame('disponible', $this->estadoDe($funcion, $asiento));

        // Liberarlo de nuevo no falla
        $this->postJson("/api/reservas/{$bloqueo['id']}/liberar", ['token' => $bloqueo['token']])->assertNoContent();
    }

    public function test_una_butaca_comprada_esta_libre_en_la_proxima_funcion(): void
    {
        $funcion = $this->crearFuncion();
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();
        $proxima = $funcion->replicate();
        $proxima->fecha_hora = now()->addDays(2);
        $proxima->save();

        $bloqueo = $this->postJson('/api/reservas/bloquear', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
        ])->json();
        $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id, 'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana', 'invitado_correo' => 'ana@test.com',
            'bloqueo_id' => $bloqueo['id'], 'bloqueo_token' => $bloqueo['token'],
        ])->assertCreated();
        $this->postJson("/api/reservas/{$bloqueo['id']}/pago", $this->tarjeta())
            ->assertCreated();

        $this->assertSame('ocupado', $this->estadoDe($funcion, $asiento));
        $this->assertSame('disponible', $this->estadoDe($proxima, $asiento));
    }
}
