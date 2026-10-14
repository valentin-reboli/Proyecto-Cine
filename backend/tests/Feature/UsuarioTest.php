<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private array $datos = [
        'name' => 'Jacobo',
        'email' => 'jacobo@cineconcordia.com',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ];

    public function test_el_admin_crea_un_usuario_con_la_contraseña_hasheada(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/usuarios', $this->datos)
            ->assertCreated()
            ->assertJsonPath('email', 'jacobo@cineconcordia.com')
            ->assertJsonMissingPath('password');

        $usuario = User::where('email', 'jacobo@cineconcordia.com')->firstOrFail();
        $this->assertTrue(Hash::check('secreto123', $usuario->password));
    }

    public function test_no_se_repite_el_email_ni_se_acepta_una_contraseña_sin_confirmar(): void
    {
        Sanctum::actingAs(User::factory()->create(['email' => 'jacobo@cineconcordia.com']));

        $this->postJson('/api/usuarios', ['password_confirmation' => 'otra'] + $this->datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_sin_token_no_se_pueden_ver_ni_crear_usuarios(): void
    {
        $this->getJson('/api/usuarios')->assertUnauthorized();
        $this->postJson('/api/usuarios', $this->datos)->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }
}
