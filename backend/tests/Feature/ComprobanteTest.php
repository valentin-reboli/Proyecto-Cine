<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Combo;
use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Reserva;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Comprobante en PDF y aviso a n8n para el correo de confirmacion.
class ComprobanteTest extends TestCase
{
    use RefreshDatabase;

    private function crearFuncion(): Funcion
    {
        $sala = Sala::create(['nombre' => 'Sala Test']);

        foreach (range(1, 3) as $numero) {
            Asiento::create(['sala_id' => $sala->id, 'fila' => 'A', 'numero' => $numero]);
        }

        $pelicula = Pelicula::create([
            'titulo' => 'Pelicula Test',
            'duracion_min' => 100,
            'en_cartelera' => true,
            'activa' => true,
            'genero_id' => Genero::create(['nombre' => 'Accion'])->id,
        ]);

        return Funcion::create([
            'pelicula_id' => $pelicula->id,
            'sala_id' => $sala->id,
            'formato_id' => Formato::create(['nombre' => '2D'])->id,
            'fecha_hora' => now()->addDay(),
            'idioma' => 'DOB',
            'precio_base' => 1000,
        ]);
    }

    private function reservar(Funcion $funcion, int $butaca, array $extra = []): int
    {
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->orderBy('numero')->get()[$butaca];

        return $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
            ...$extra,
        ])->assertCreated()->json('id');
    }

    public function test_el_comprobante_en_pdf_solo_existe_para_entradas_pagadas(): void
    {
        $funcion = $this->crearFuncion();
        $reservaId = $this->reservar($funcion, 0);
        $codigo = $this->pagar($reservaId)->assertCreated()->json('reserva.codigo_qr');

        $respuesta = $this->get("/api/entradas/comprobante/{$codigo}.pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertStringContainsString("comprobante-cine-concordia-{$reservaId}.pdf", $respuesta->headers->get('Content-Disposition'));

        $this->get('/api/entradas/comprobante/inexistente.pdf')->assertNotFound();

        Reserva::whereKey($reservaId)->update(['estado' => 'cancelada']);
        $this->get("/api/entradas/comprobante/{$codigo}.pdf")->assertNotFound();
    }

    public function test_n8n_recibe_el_pdf_el_descuento_usado_los_importes_y_el_token(): void
    {
        config([
            'services.n8n.compra_webhook' => 'https://n8n.test/compra',
            'services.n8n.token' => 'secreto-n8n',
        ]);
        Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);

        $funcion = $this->crearFuncion();
        $this->pagar($this->reservar($funcion, 0))->assertCreated();
        $regalo = Reserva::first()->descuentoGenerado;

        $combo = Combo::create(['nombre' => 'Pochoclo', 'precio' => 500, 'activo' => true]);
        $reservaId = $this->reservar($funcion, 1, [
            'codigo_descuento' => $regalo->codigo,
            'combos' => [['combo_id' => $combo->id, 'cantidad' => 2]],
        ]);
        $codigo = $this->pagar($reservaId)->assertCreated()->json('reserva.codigo_qr');

        Http::assertSent(fn ($r) => $r['reserva']['id'] === $reservaId
            && $r->hasHeader('X-Cine-Token', 'secreto-n8n')
            && str_ends_with($r['comprobante_pdf_url'], "/api/entradas/comprobante/{$codigo}.pdf")
            && $r['descuento_aplicado']['codigo'] === $regalo->codigo
            && $r['importes'] == ['entradas' => 1000, 'combos' => 1000, 'descuento' => 100, 'total' => 1900]
            && $r['combos'][0]['cantidad'] === 2
            && $r['descuento']['codigo'] !== $regalo->codigo
            && str_contains($r['mensaje'], 'Descuento aplicado: '.$regalo->codigo));
    }

    public function test_los_comprobantes_que_n8n_no_recibio_se_reintentan(): void
    {
        config(['services.n8n.compra_webhook' => 'https://n8n.test/compra']);
        Http::fakeSequence('n8n.test/*')->push('caido', 500)->push(['ok' => true]);

        $funcion = $this->crearFuncion();
        $reservaId = $this->reservar($funcion, 0);
        $this->pagar($reservaId)->assertCreated()->assertJsonPath('reserva.comprobante_enviado_en', null);

        $this->artisan('comprobantes:reenviar')
            ->expectsOutputToContain('Se enviaron 1 de 1')
            ->assertSuccessful();
        $this->assertNotNull(Reserva::find($reservaId)->comprobante_enviado_en);

        // Ya enviado: no se vuelve a mandar.
        $this->artisan('comprobantes:reenviar')->expectsOutputToContain('Se enviaron 0 de 0');
        Http::assertSentCount(2);
    }

    public function test_no_se_reintentan_comprobantes_de_funciones_que_ya_pasaron(): void
    {
        config(['services.n8n.compra_webhook' => 'https://n8n.test/compra']);
        Http::fake(['n8n.test/*' => Http::response('caido', 500)]);

        $funcion = $this->crearFuncion();
        $this->pagar($this->reservar($funcion, 0))->assertCreated();
        $funcion->update(['fecha_hora' => now()->subHour()]);

        $this->artisan('comprobantes:reenviar')->expectsOutputToContain('Se enviaron 0 de 0');
    }
}
