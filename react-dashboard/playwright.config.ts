import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config — smoke tests for the dashboard.
 * Runs against the Vite dev server (or built preview).
 *
 * Usage:
 *   npm run test:e2e       — headless CI mode
 *   npm run test:e2e:ui    — interactive UI runner
 *
 * The webServer block will auto-start `npm run dev` before tests.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://localhost:5175',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: 'npm run dev',
    port: 5175,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
