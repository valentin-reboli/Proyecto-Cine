<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Formato;
use App\Models\Funcion;
use App\Models\Genero;
use App\Models\Pelicula;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $pagoResponse = $this->postJson("/api/reservas/{$reservaId}/pago", [
            'correo' => 'ana@test.com',
            'metodo' => 'tarjeta',
        ]);

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
}
