import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end suite for the key flows. It runs against its own database and
 * server (docker/scripts/e2e.sh), never against development data.
 *
 *   make e2e                         reset the database, build and run
 *   E2E_BASE_URL=http://… npx playwright test   use a server that is already up
 */
const port = Number(process.env.E2E_PORT ?? 8001);
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${port}`;

export default defineConfig({
    testDir: 'tests/e2e',
    // One worker: the specs share one seeded database and one PHP server.
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    timeout: 30_000,
    expect: { timeout: 10_000 },
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'never' }]],
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL,
        locale: 'es-ES',
        timezoneId: 'Europe/Madrid',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        {
            name: 'mobile',
            use: { ...devices['Pixel 7'] },
            // Mobile-first layout: the public listing must work on a phone.
            testMatch: /(jobs|mobile-layout)\.spec\.ts/,
        },
    ],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
              command:
                  process.env.E2E_SERVER_COMMAND ??
                  'sh docker/scripts/e2e-docker-server.sh',
              url: `${baseURL}/up`,
              reuseExistingServer: !process.env.CI,
              timeout: 120_000,
              // SIGTERM (not the default SIGKILL) lets the script remove its container.
              gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 },
          },
});
