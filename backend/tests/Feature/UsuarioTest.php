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

    public function test_cambiar_la_contraseña_cierra_las_otras_sesiones_pero_no_la_actual(): void
    {
        $admin = User::factory()->create();
        $tokenAdmin = $admin->createToken('admin')->plainTextToken;
        $admin->createToken('otra-pc');

        $this->withToken($tokenAdmin)
            ->putJson("/api/usuarios/{$admin->id}/password", [
                'password' => 'nueva-clave',
                'password_confirmation' => 'nueva-clave',
            ])
            ->assertNoContent();

        $this->assertTrue(Hash::check('nueva-clave', $admin->fresh()->password));
        $this->assertSame(1, $admin->tokens()->count());
        $this->withToken($tokenAdmin)->getJson('/api/usuarios')->assertOk();
    }

    public function test_el_admin_cambia_la_contraseña_de_otro_usuario(): void
    {
        $otro = User::factory()->create();
        $otro->createToken('admin');

        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/usuarios/{$otro->id}/password", ['password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->putJson("/api/usuarios/{$otro->id}/password", [
            'password' => 'nueva-clave',
            'password_confirmation' => 'nueva-clave',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('nueva-clave', $otro->fresh()->password));
        $this->assertSame(0, $otro->tokens()->count());
    }
}
