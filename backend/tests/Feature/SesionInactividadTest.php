<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SesionInactividadTest extends TestCase
{
    use RefreshDatabase;

    private function tokenDelAdmin(): string
    {
        $admin = User::factory()->create(['email' => 'admin@cine.test', 'password' => 'secreto123']);

        return $admin->createToken('admin')->plainTextToken;
    }

    private function conToken(string $token)
    {
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_el_token_sirve_mientras_no_pasen_4_horas_sin_usarlo(): void
    {
        $token = $this->tokenDelAdmin();

        $this->travel(3)->hours();
        $this->conToken($token)->getJson('/api/user')->assertOk();
    }

    public function test_despues_de_4_horas_sin_usarlo_el_token_vence_y_se_borra(): void
    {
        $token = $this->tokenDelAdmin();

        $this->travel(4)->hours();
        $this->travel(1)->minutes();
        $this->conToken($token)->getJson('/api/user')->assertUnauthorized();

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_usar_el_panel_renueva_el_plazo(): void
    {
        $token = $this->tokenDelAdmin();

        // Usa el panel cada 3 horas durante 9 horas: nunca pasan 4 horas seguidas sin uso.
        foreach (range(1, 3) as $vuelta) {
            $this->travel(3)->hours();
            $this->conToken($token)->getJson('/api/user')->assertOk();
            $this->app['auth']->forgetGuards();
        }

        // Y si despues se deja de usar, vence.
        $this->travel(5)->hours();
        $this->conToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_el_plazo_se_puede_configurar(): void
    {
        config(['cine.admin_inactividad_minutos' => 30]);
        $token = $this->tokenDelAdmin();

        $this->travel(31)->minutes();
        $this->conToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}
