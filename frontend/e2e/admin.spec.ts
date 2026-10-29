import { test, expect, loginAdmin, ADMIN } from './helpers';

test.describe('Panel de administrador', () => {
  test('sin sesion /admin redirige al login', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: 'Iniciar sesión' })).toBeVisible();
  });

  test('con contraseña incorrecta muestra el error', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(ADMIN.email);
    await page.locator('input[name="password"]').fill('incorrecta');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page.locator('.login-error')).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
  });

  test('login y recorrido por todas las secciones', async ({ page }) => {
    await loginAdmin(page);

    // Cartelera
    await expect(page.getByRole('heading', { name: 'Cartelera', level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: /En cartelera \(4\)/ })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Próximamente \(2\)/ })).toBeVisible();

    // Funciones y salas
    await page.getByRole('link', { name: 'Funciones' }).click();
    await expect(page.getByRole('heading', { name: 'Funciones y salas' })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Próximas funciones \(\d+\)/ })).toBeVisible();
    await page.getByRole('tab', { name: 'Salas y butacas' }).click();
    await expect(page.getByRole('heading', { name: /Salas \([1-9]\d*\)/ })).toBeVisible();
    await page.getByRole('tab', { name: 'Formatos' }).click();
    await expect(page.getByRole('heading', { name: /Formatos \([1-9]\d*\)/ })).toBeVisible();

    // Combos
    await page.getByRole('link', { name: 'Combos' }).click();
    await expect(page.getByRole('heading', { name: 'Combos y snacks' })).toBeVisible();

    // Campañas
    await page.getByRole('link', { name: 'Campañas' }).click();
    await expect(page.getByRole('heading', { name: 'Campañas', level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Campañas \(\d+\)/ })).toBeVisible();
    await page.getByRole('button', { name: /^Contactos/ }).click();
    await expect(page.getByRole('heading', { name: 'Contactos de compradores' })).toBeVisible();

    // Configuracion (usuarios del panel)
    await page.getByRole('link', { name: 'Configuración' }).click();
    await expect(page.getByRole('heading', { name: 'Configuración', level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Usuarios del panel \(\d+\)/ })).toBeVisible();
    await expect(page.getByText(ADMIN.email)).toBeVisible();
  });

  test('crear un combo y verlo ofrecido en la compra', async ({ page }) => {
    await loginAdmin(page);
    await page.getByRole('link', { name: 'Combos' }).click();
    await expect(page.getByRole('heading', { name: 'Combos y snacks' })).toBeVisible();

    await page.getByRole('button', { name: '+ Nuevo combo' }).click();
    await page.locator('input[name="nombre"]').fill('Combo E2E');
    await page.locator('input[name="descripcion"]').fill('Pochoclo + gaseosa');
    await page.locator('input[name="precio"]').fill('4500');
    const guardado = page.waitForResponse((r) => r.url().endsWith('/api/combos') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Guardar' }).click();
    expect((await guardado).status()).toBe(201);
    await expect(page.locator('.admin-table').getByText('Combo E2E')).toBeVisible();

    // La lista publica de combos (la que usa la compra) lo incluye.
    const publicos = await page.request.get('http://127.0.0.1:8000/api/combos');
    expect((await publicos.json()).map((c: { nombre: string }) => c.nombre)).toContain('Combo E2E');
  });

  test('despues de 4 horas sin usar el panel la sesion se cierra sola', async ({ page }) => {
    await page.clock.install();
    await loginAdmin(page);
    await expect(page.getByRole('heading', { name: 'Cartelera', level: 1 })).toBeVisible();

    // 3 horas sin tocar nada: sigue adentro.
    await page.clock.fastForward('03:00:00');
    await expect(page).toHaveURL(/\/admin$/);

    // Pasadas las 4 horas: vuelve al login con el aviso.
    await page.clock.fastForward('01:02:00');
    await expect(page).toHaveURL(/\/login\?motivo=inactividad$/);
    await expect(page.locator('.login-aviso')).toHaveText('Tu sesión se cerró por inactividad. Volvé a ingresar.');

    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login$/);
  });

  test('si el servidor ya no acepta el token, vuelve al login', async ({ page }) => {
    await loginAdmin(page);
    // Token vencido en el backend (equivale a 4 horas sin uso del lado del servidor).
    await page.evaluate(() => localStorage.setItem('cc_admin_token', '999|token-vencido'));
    await page.reload();
    await expect(page).toHaveURL(/\/login\?motivo=inactividad$/);
    await expect(page.locator('.login-aviso')).toBeVisible();
  });

  test('cerrar sesion vuelve a pedir login', async ({ page }) => {
    await loginAdmin(page);
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await expect(page).toHaveURL(/\/login$/);
    // El token se borro: volver a /admin pide login de nuevo.
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login$/);
  });
});
