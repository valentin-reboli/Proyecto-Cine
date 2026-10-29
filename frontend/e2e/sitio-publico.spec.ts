import { test, expect } from './helpers';

test.describe('Sitio publico', () => {
  test('la home muestra la cartelera y los proximos estrenos desde la API', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Cartelera' })).toBeVisible();
    // Peliculas en cartelera cargadas por los seeders.
    for (const titulo of ['Duro de Matar', 'Casablanca', 'El Exorcista']) {
      await expect(page.locator('#cartelera, main').getByText(titulo).first()).toBeVisible();
    }
    // Proximamente (en_cartelera = false).
    await expect(page.getByRole('heading', { name: 'Próximamente' })).toBeVisible();
    await expect(page.getByText('2001: Odisea del Espacio').first()).toBeVisible();
  });

  test('el buscador de la cartelera filtra por titulo', async ({ page }) => {
    await page.goto('/');
    await page.getByPlaceholder('Buscar película...').fill('Casab');
    const cartelera = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Cartelera' }) });
    await expect(cartelera.getByText('Casablanca').first()).toBeVisible();
    await expect(cartelera.getByText('Duro de Matar')).toHaveCount(0);
  });

  test('el detalle de una pelicula muestra sus funciones', async ({ page }) => {
    await page.goto('/');
    const cartelera = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Cartelera' }) });
    await cartelera.getByText('Casablanca').first().click();

    const detalle = page.getByRole('dialog');
    await expect(detalle.getByRole('heading', { name: 'Casablanca' })).toBeVisible();
    await expect(detalle.locator('.detalle-chip').first()).toBeVisible();
    await detalle.getByRole('button', { name: 'Cerrar' }).click();
    await expect(detalle).toHaveCount(0);
  });
});
