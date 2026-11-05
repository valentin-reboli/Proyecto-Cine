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

// Comprobante en PDF de una compra pagada.
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
}
