import { test as base, expect, Page } from '@playwright/test';

export const API = 'http://127.0.0.1:8000/api';
export const ADMIN = { email: 'admin@e2e.test', password: 'admin-e2e-1234' };

// Cada test falla si la API devolvio algun error 5xx o un 404 de ruta inexistente
// (por ejemplo, si un controlador quedo mal referenciado despues de moverlo).
export const test = base.extend<{ erroresApi: string[] }>({
  erroresApi: [
    async ({ page }, use) => {
      const errores: string[] = [];
      page.on('response', (res) => {
        const url = res.url();
        if (!url.startsWith(API)) return;
        if (res.status() >= 500) errores.push(`${res.status()} ${res.request().method()} ${url}`);
      });
      await use(errores);
      expect(errores, 'La API devolvio errores del servidor').toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };

export async function loginAdmin(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(ADMIN.email);
  await page.locator('input[name="password"]').fill(ADMIN.password);
  await page.getByRole('button', { name: 'Ingresar' }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

export async function tokenAdmin(request: import('@playwright/test').APIRequestContext): Promise<string> {
  const res = await request.post(`${API}/login`, { data: ADMIN, headers: { Accept: 'application/json' } });
  expect(res.status()).toBe(200);
  return (await res.json()).token;
}
