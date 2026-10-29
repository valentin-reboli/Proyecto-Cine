import { test, expect, API } from './helpers';

test.describe('Compra de entradas', () => {
  test('flujo completo: pelicula, funcion, butaca, datos y reserva creada', async ({ page, request }) => {
    await page.goto('/reserva');

    // Paso 1: pelicula
    await expect(page.getByRole('heading', { name: 'Elegí la película' })).toBeVisible();
    await page.locator('.pelicula-btn').filter({ hasText: 'Casablanca' }).click();

    // Paso 2: funcion (la primera disponible)
    await expect(page.getByRole('heading', { name: 'Elegí tu función' })).toBeVisible();
    const asientosCargados = page.waitForResponse(
      (r) => /\/api\/funciones\/\d+\/asientos/.test(r.url()) && r.status() === 200,
    );
    await page.locator('button:not([disabled])').filter({ hasText: /\d{1,2}:\d{2}/ }).first().click();
    await asientosCargados;

    // Paso 3: butacas
    await expect(page.getByRole('heading', { name: 'Elegí tus butacas' })).toBeVisible();
    await page.locator('.seat-btn:not([disabled])').first().click();
    await expect(page.locator('.seat-btn.selected')).toHaveCount(1);

    const bloqueo = page.waitForResponse((r) => r.url().endsWith('/api/reservas/bloquear'));
    await page.getByRole('button', { name: 'Continuar' }).click();
    expect((await bloqueo).status()).toBe(201);

    // Paso opcional: combos (solo aparece si hay combos activos)
    const combos = page.getByRole('heading', { name: '¿Querés agregar algo para comer o tomar?' });
    const datos = page.getByRole('heading', { name: 'Tus datos' });
    await expect(combos.or(datos)).toBeVisible();
    if (await combos.isVisible()) {
      await page.getByRole('button', { name: 'Continuar' }).click();
    }

    // Paso 4: datos del comprador
    await expect(datos).toBeVisible();
    await page.locator('input[name="nombre"]').fill('Cliente E2E');
    await page.locator('input[name="correo"]').fill('cliente@e2e.test');
    await page.locator('input[name="telefono"]').fill('3435551234');
    await page.getByRole('button', { name: 'Continuar' }).click();

    // Paso 5: pago. Se crea la reserva en el backend (Mercado Pago no se prueba aca).
    await expect(page.getByRole('heading', { name: 'Pago' })).toBeVisible();
    await expect(page.locator('.payment-method-btn')).toHaveCount(2);

    const reserva = page.waitForResponse((r) => r.url().endsWith('/api/reservas') && r.request().method() === 'POST');
    await page.getByRole('button', { name: /Pagar con tarjeta/ }).click();
    const res = await reserva;
    expect(res.status()).toBe(201);
    const { id } = await res.json();

    // La reserva quedo guardada y se puede consultar con el correo del comprador.
    const consulta = await request.get(`${API}/reservas/${id}`, {
      params: { correo: 'cliente@e2e.test' },
      headers: { Accept: 'application/json' },
    });
    expect(consulta.status()).toBe(200);
  });
});
