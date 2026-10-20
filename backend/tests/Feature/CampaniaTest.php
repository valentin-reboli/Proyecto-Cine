<?php

namespace Tests\Feature;

use App\Models\Campania;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaniaTest extends TestCase
{
    use RefreshDatabase;

    public function test_requiere_login(): void
    {
        $this->getJson('/api/campanias')->assertUnauthorized();
        $this->getJson('/api/clientes')->assertUnauthorized();
    }

    public function test_crear_programada_y_borrador(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/campanias', ['nombre' => 'A', 'canal' => 'whatsapp', 'mensaje' => 'Hola'])
            ->assertCreated()->assertJsonPath('estado', 'borrador');

        $this->postJson('/api/campanias', [
            'nombre' => 'B', 'canal' => 'email', 'asunto' => 'x', 'mensaje' => 'Hola',
            'programada_para' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('estado', 'programada');

        $this->postJson('/api/campanias', ['nombre' => 'C', 'canal' => 'email', 'mensaje' => 'Hola'])
            ->assertUnprocessable();
    }

    public function test_envio_solo_a_quienes_aceptaron(): void
    {
        config(['services.n8n.campanias_webhook' => 'https://n8n.test/hook']);
        Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);
        Sanctum::actingAs(User::factory()->create());

        Cliente::create(['nombre' => 'Si', 'correo' => 'si@t.com', 'telefono' => '1', 'acepta_marketing' => true]);
        Cliente::create(['nombre' => 'No', 'correo' => 'no@t.com', 'telefono' => '2', 'acepta_marketing' => false]);
        $campania = Campania::create(['nombre' => 'A', 'canal' => 'email', 'asunto' => 's', 'mensaje' => 'm']);

        $this->postJson("/api/campanias/{$campania->id}/enviar")
            ->assertOk()->assertJsonPath('estado', 'enviada')->assertJsonPath('destinatarios', 1);

        Http::assertSent(fn ($r) => count($r['destinatarios']) === 1 && $r['destinatarios'][0]['correo'] === 'si@t.com');
    }

    public function test_sin_webhook_queda_en_error(): void
    {
        config(['services.n8n.campanias_webhook' => null]);
        Sanctum::actingAs(User::factory()->create());
        Cliente::create(['nombre' => 'Si', 'correo' => 'si@t.com', 'acepta_marketing' => true]);
        $campania = Campania::create(['nombre' => 'A', 'canal' => 'email', 'asunto' => 's', 'mensaje' => 'm']);

        $this->postJson("/api/campanias/{$campania->id}/enviar")->assertJsonPath('estado', 'error');
    }
}
