<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

// Red de seguridad para reorganizar carpetas (mover controladores, Swagger, factories):
// si algo queda mal referenciado, estos tests fallan antes de llegar al navegador.
class EstructuraTest extends TestCase
{
    use RefreshDatabase;

    // Rutas de la API que no se documentan en Swagger a proposito.
    private const SIN_DOCUMENTAR = [
        'GET /api/user',
        'GET /api/admin/funciones',
        'GET /api/documentation',
        'GET /api/oauth2-callback',
    ];

    public function test_cada_ruta_de_la_api_apunta_a_un_controlador_y_metodo_que_existen(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => Str::startsWith($ruta->uri(), 'api/'))
            ->filter(fn ($ruta) => Str::startsWith($ruta->getActionName(), 'App\\'));

        $this->assertNotEmpty($rutas);

        foreach ($rutas as $ruta) {
            [$clase, $metodo] = array_pad(explode('@', $ruta->getActionName()), 2, '__invoke');

            $this->assertTrue(class_exists($clase), "No existe el controlador {$clase} (ruta {$ruta->uri()})");
            $this->assertTrue(method_exists($clase, $metodo), "No existe {$clase}::{$metodo} (ruta {$ruta->uri()})");
        }
    }

    public function test_swagger_se_genera_y_documenta_todas_las_rutas_de_la_api(): void
    {
        $this->assertSame(0, Artisan::call('l5-swagger:generate'));

        $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

        $this->assertSame('Cine Concordia API', $spec['info']['title']);
        $this->assertArrayHasKey('sanctum', $spec['components']['securitySchemes']);

        $documentadas = collect($spec['paths'])
            ->flatMap(fn ($metodos, $path) => collect(array_keys($metodos))->map(fn ($m) => strtoupper($m).' '.$path))
            ->all();

        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            if (! Str::startsWith($ruta->uri(), 'api/')) {
                continue;
            }
            $operacion = $ruta->methods()[0].' /'.$ruta->uri();
            if (in_array($operacion, self::SIN_DOCUMENTAR, true)) {
                continue;
            }
            $this->assertContains($operacion, $documentadas, "Swagger no documenta {$operacion}");
        }
    }

    public function test_los_seeders_cargan_los_datos_de_ejemplo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->getJson('/api/peliculas')->assertOk()->assertJsonFragment(['titulo' => 'Casablanca']);
        $this->getJson('/api/salas')->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
