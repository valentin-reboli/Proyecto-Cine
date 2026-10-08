<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntradaValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearFuncion(): Funcion
    {
        $genero = Genero::create(['nombre' => 'Accion']);
        $sala = Sala::create(['nombre' => 'Sala Test']);
        $formato = Formato::create(['nombre' => '2D']);

        Asiento::create(['sala_id' => $sala->id, 'fila' => 'A', 'numero' => 1]);

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

    // Reserva + pago por la API, igual que lo hace el cliente.
    private function comprarEntrada(Funcion $funcion): Reserva
    {
        $asiento = Asiento::where('sala_id', $funcion->sala_id)->first();

        $reservaId = $this->postJson('/api/reservas', [
            'funcion_id' => $funcion->id,
            'asientos' => [$asiento->id],
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
        ])->assertCreated()->json('id');

        $this->postJson("/api/reservas/{$reservaId}/pago", [
            'correo' => 'ana@test.com',
            'metodo' => 'tarjeta',
        ])->assertCreated();

        return Reserva::findOrFail($reservaId);
    }

    public function test_requiere_login(): void
    {
        $this->postJson('/api/entradas/validar', ['codigo_qr' => 'x'])->assertUnauthorized();
    }

    public function test_entrada_pagada_es_valida_y_queda_usada(): void
    {
        $reserva = $this->comprarEntrada($this->crearFuncion());
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entradas/validar', ['codigo_qr' => $reserva->codigo_qr])
            ->assertOk()
            ->assertJsonPath('id', $reserva->id)
            ->assertJsonPath('funcion.pelicula.titulo', 'Pelicula Test');

        $this->assertNotNull($reserva->fresh()->usada_en);
    }

    public function test_no_se_puede_usar_dos_veces(): void
    {
        $reserva = $this->comprarEntrada($this->crearFuncion());
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entradas/validar', ['codigo_qr' => $reserva->codigo_qr])->assertOk();

        $this->postJson('/api/entradas/validar', ['codigo_qr' => $reserva->codigo_qr])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'usada_en']);
    }

    public function test_codigo_inexistente(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entradas/validar', ['codigo_qr' => 'no-existe'])->assertNotFound();
    }

    public function test_reserva_pendiente_no_es_valida(): void
    {
        $funcion = $this->crearFuncion();
        $reserva = Reserva::create([
            'funcion_id' => $funcion->id,
            'invitado_nombre' => 'Ana',
            'invitado_correo' => 'ana@test.com',
            'total' => 1000,
            'estado' => 'pendiente',
            'codigo_qr' => 'codigo-pendiente',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entradas/validar', ['codigo_qr' => $reserva->codigo_qr])->assertStatus(422);
        $this->assertNull($reserva->fresh()->usada_en);
    }

    public function test_funcion_cancelada_no_es_valida(): void
    {
        $funcion = $this->crearFuncion();
        $reserva = $this->comprarEntrada($funcion);
        $funcion->update(['cancelada' => true]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entradas/validar', ['codigo_qr' => $reserva->codigo_qr])->assertStatus(422);
        $this->assertNull($reserva->fresh()->usada_en);
    }
}
