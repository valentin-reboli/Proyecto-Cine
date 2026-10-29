import { defineConfig, devices } from '@playwright/test';

// Tests E2E: levantan el backend (con una base SQLite de prueba) y el frontend,
// y recorren la pagina en un navegador real. Correr con: npm run e2e
export default defineConfig({
  testDir: './e2e',
  // Comparten la misma base, asi que van de a uno y en orden.
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:4200',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: 'bash e2e/levantar-backend.sh',
      url: 'http://127.0.0.1:8000/api/estado',
      // Siempre uno nuevo: los tests necesitan la base recien sembrada.
      reuseExistingServer: false,
      timeout: 120_000,
      stdout: 'ignore',
      stderr: 'pipe',
    },
    {
      command: 'npx ng serve --port 4200',
      url: 'http://localhost:4200',
      reuseExistingServer: true,
      timeout: 180_000,
    },
  ],
});
