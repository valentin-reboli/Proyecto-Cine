<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'cliente-de-prueba']);
    }

    private function googleDevuelve(array $payload, int $status = 200): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($payload, $status)]);
    }

    public function test_un_admin_registrado_entra_con_su_cuenta_de_google(): void
    {
        User::factory()->create(['email' => 'jacobo@cineconcordia.com']);
        $this->googleDevuelve(['aud' => 'cliente-de-prueba', 'email' => 'jacobo@cineconcordia.com', 'email_verified' => 'true']);

        $this->postJson('/api/login/google', ['credential' => 'jwt-de-google'])
            ->assertOk()
            ->assertJsonPath('user.email', 'jacobo@cineconcordia.com')
            ->assertJsonStructure(['token']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tokeninfo') && $request['id_token'] === 'jwt-de-google');
    }

    public function test_no_se_crean_cuentas_para_emails_que_no_son_admin(): void
    {
        $this->googleDevuelve(['aud' => 'cliente-de-prueba', 'email' => 'otro@gmail.com', 'email_verified' => 'true']);

        $this->postJson('/api/login/google', ['credential' => 'jwt-de-google'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_se_rechaza_un_token_de_otra_app_sin_verificar_o_invalido(): void
    {
        User::factory()->create(['email' => 'jacobo@cineconcordia.com']);

        $this->googleDevuelve(['aud' => 'otra-app', 'email' => 'jacobo@cineconcordia.com', 'email_verified' => 'true']);
        $this->postJson('/api/login/google', ['credential' => 'jwt'])->assertUnprocessable();

        $this->googleDevuelve(['aud' => 'cliente-de-prueba', 'email' => 'jacobo@cineconcordia.com', 'email_verified' => 'false']);
        $this->postJson('/api/login/google', ['credential' => 'jwt'])->assertUnprocessable();

        $this->googleDevuelve(['error' => 'invalid_token'], 400);
        $this->postJson('/api/login/google', ['credential' => 'jwt'])->assertUnprocessable();

        $this->postJson('/api/login/google', [])->assertUnprocessable()->assertJsonValidationErrors('credential');
    }

    public function test_si_google_no_responde_devuelve_un_error_claro(): void
    {
        Http::fake(fn () => throw new ConnectionException('sin conexion'));

        $this->postJson('/api/login/google', ['credential' => 'jwt'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');
    }
}
