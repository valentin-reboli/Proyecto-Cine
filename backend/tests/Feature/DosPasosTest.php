<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DosPasosTest extends TestCase
{
    use RefreshDatabase;

    private function codigoActual(string $secreto): string
    {
        return (new Google2FA)->getCurrentOtp($secreto);
    }

    private function usuarioConDosPasos(array $atributos = []): array
    {
        $usuario = User::factory()->create($atributos);
        $secreto = (new Google2FA)->generateSecretKey(32);
        $usuario->forceFill(['dos_pasos_secreto' => $secreto, 'dos_pasos_activado_en' => now()])->save();

        return [$usuario, $secreto];
    }

    public function test_el_admin_activa_google_authenticator_con_el_qr_y_el_primer_codigo(): void
    {
        $usuario = User::factory()->create(['email' => 'naza@cineconcordia.com']);
        Sanctum::actingAs(User::factory()->create());

        $respuesta = $this->postJson("/api/usuarios/{$usuario->id}/dos-pasos")
            ->assertOk()
            ->assertJsonStructure(['secreto', 'otpauth_url', 'qr']);

        $secreto = $respuesta->json('secreto');
        $this->assertStringStartsWith('otpauth://totp/', $respuesta->json('otpauth_url'));
        $this->assertStringContainsString('naza%40cineconcordia.com', $respuesta->json('otpauth_url'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $respuesta->json('qr'));

        // Hasta confirmar, el login no pide codigo
        $this->assertFalse($usuario->fresh()->tieneDosPasos());

        $this->postJson("/api/usuarios/{$usuario->id}/dos-pasos/confirmar", ['codigo' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');

        $this->postJson("/api/usuarios/{$usuario->id}/dos-pasos/confirmar", ['codigo' => $this->codigoActual($secreto)])
            ->assertOk()
            ->assertJsonPath('dos_pasos', true);

        $this->assertTrue($usuario->fresh()->tieneDosPasos());
        $this->getJson('/api/usuarios')->assertOk()->assertJsonFragment(['email' => 'naza@cineconcordia.com', 'dos_pasos' => true]);
    }

    public function test_el_secreto_se_guarda_encriptado_y_no_sale_en_las_respuestas(): void
    {
        [$usuario, $secreto] = $this->usuarioConDosPasos();
        Sanctum::actingAs($usuario);

        $this->assertNotSame($secreto, DB::table('users')->where('id', $usuario->id)->value('dos_pasos_secreto'));
        $this->getJson('/api/usuarios')->assertOk()->assertJsonMissingPath('0.dos_pasos_secreto');
        $this->getJson('/api/user')->assertOk()->assertJsonMissingPath('dos_pasos_secreto');
    }

    public function test_no_se_genera_otro_qr_si_ya_esta_activado_y_se_puede_desactivar(): void
    {
        [$usuario] = $this->usuarioConDosPasos();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/usuarios/{$usuario->id}/dos-pasos")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dos_pasos');

        $this->deleteJson("/api/usuarios/{$usuario->id}/dos-pasos")
            ->assertOk()
            ->assertJsonPath('dos_pasos', false);

        $this->assertFalse($usuario->fresh()->tieneDosPasos());
        $this->assertNull($usuario->fresh()->dos_pasos_secreto);
    }

    public function test_sin_token_no_se_toca_la_verificacion_en_dos_pasos(): void
    {
        $usuario = User::factory()->create();

        $this->postJson("/api/usuarios/{$usuario->id}/dos-pasos")->assertUnauthorized();
        $this->postJson("/api/usuarios/{$usuario->id}/dos-pasos/confirmar", ['codigo' => '123456'])->assertUnauthorized();
        $this->deleteJson("/api/usuarios/{$usuario->id}/dos-pasos")->assertUnauthorized();
    }

    public function test_el_login_con_contraseña_pide_el_codigo_si_tiene_google_authenticator(): void
    {
        [$usuario, $secreto] = $this->usuarioConDosPasos(['email' => 'jacobo@cineconcordia.com']);
        $credenciales = ['email' => 'jacobo@cineconcordia.com', 'password' => 'password'];

        $this->postJson('/api/login', $credenciales)
            ->assertOk()
            ->assertJsonPath('requiere_codigo', true)
            ->assertJsonMissingPath('token');

        $this->postJson('/api/login', $credenciales + ['codigo' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');

        $this->assertSame(0, $usuario->tokens()->count());

        $this->postJson('/api/login', $credenciales + ['codigo' => $this->codigoActual($secreto)])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);

        // Contraseña mal: no llega a pedir el codigo
        $this->postJson('/api/login', ['email' => 'jacobo@cineconcordia.com', 'password' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_el_login_sin_google_authenticator_sigue_devolviendo_el_token_directo(): void
    {
        User::factory()->create(['email' => 'admin@cineconcordia.com']);

        $this->postJson('/api/login', ['email' => 'admin@cineconcordia.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_el_login_con_google_tambien_pide_el_codigo(): void
    {
        config(['services.google.client_id' => 'cliente-de-prueba']);
        [, $secreto] = $this->usuarioConDosPasos(['email' => 'jacobo@cineconcordia.com']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'aud' => 'cliente-de-prueba',
                'email' => 'jacobo@cineconcordia.com',
                'email_verified' => 'true',
            ]),
        ]);

        $this->postJson('/api/login/google', ['credential' => 'jwt-de-google'])
            ->assertOk()
            ->assertJsonPath('requiere_codigo', true)
            ->assertJsonMissingPath('token');

        $this->postJson('/api/login/google', ['credential' => 'jwt-de-google', 'codigo' => $this->codigoActual($secreto)])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }
}
