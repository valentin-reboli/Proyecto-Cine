import { test, expect, API, tokenAdmin } from './helpers';

const JSON_HEADERS = { Accept: 'application/json' };

// Cada endpoint pasa por su controlador: si alguno queda mal referenciado
// (namespace, nombre de clase o metodo) estas llamadas lo detectan.
test.describe('API', () => {
  test('endpoints publicos responden 200', async ({ request }) => {
    // La cartelera trae cada pelicula con sus funciones.
    const peliculas = await (await request.get(`${API}/peliculas`)).json();
    const funcion = peliculas.find((p: { funciones: unknown[] }) => p.funciones.length > 0).funciones[0];

    for (const path of [
      '/estado',
      '/peliculas',
      `/funciones/${funcion.id}/asientos`,
      '/generos',
      '/salas',
      '/formatos',
      '/combos',
      '/pagos/config',
    ]) {
      const res = await request.get(`${API}${path}`, { headers: JSON_HEADERS });
      expect(res.status(), `GET ${path}`).toBe(200);
    }
  });

  test('endpoints de admin piden token y con token responden 200', async ({ request }) => {
    const paths = [
      '/user',
      '/peliculas/inactivas',
      '/admin/funciones',
      '/admin/combos',
      '/usuarios',
    ];

    for (const path of paths) {
      const res = await request.get(`${API}${path}`, { headers: JSON_HEADERS });
      expect(res.status(), `GET ${path} sin token`).toBe(401);
    }

    const token = await tokenAdmin(request);
    for (const path of paths) {
      const res = await request.get(`${API}${path}`, { headers: { ...JSON_HEADERS, Authorization: `Bearer ${token}` } });
      expect(res.status(), `GET ${path} con token`).toBe(200);
    }
  });

  test('Swagger: la documentacion carga y tiene todos los endpoints', async ({ page, request }) => {
    const docs = await request.get('http://127.0.0.1:8000/docs?api-docs.json');
    expect(docs.status()).toBe(200);
    const spec = await docs.json();

    expect(spec.info.title).toBe('Cine Concordia API');
    expect(spec.components.securitySchemes.sanctum).toBeTruthy();
    // Lo que se saco ya no aparece en la documentacion.
    for (const quitado of ['/api/campanias', '/api/reservas/{reserva}/pago', '/api/reservas/{reserva}/cancelar', '/api/admin/reservas']) {
      expect(Object.keys(spec.paths)).not.toContain(quitado);
    }
    for (const path of [
      '/api/login',
      '/api/peliculas',
      '/api/funciones',
      '/api/reservas',
      '/api/combos',
      '/api/usuarios',
    ]) {
      expect(Object.keys(spec.paths), `Swagger documenta ${path}`).toContain(path);
    }

    await page.goto('http://127.0.0.1:8000/api/documentation');
    await expect(page.locator('.swagger-ui .info .title')).toContainText('Cine Concordia API');
  });
});
