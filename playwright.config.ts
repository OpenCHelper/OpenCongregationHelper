import { defineConfig, devices } from '@playwright/test';

/**
 * The end-to-end tests (see the "End-to-end tests" section of `README.md`).
 *
 * Two instances of the application are served by `tests/e2e/support/serve.sh`, each with its own `.env`, storage and
 * database: `app`, installed and seeded with `E2eSeeder`, and `setup`, empty, for the setup wizard.
 */
const appPort = Number(process.env.E2E_APP_PORT ?? 8010);
// The name of the seeded instance (its `.env`, storage and database), e.g. to run a second one next to the first
export const appInstance = process.env.E2E_APP_INSTANCE ?? 'app';
const setupPort = Number(process.env.E2E_SETUP_PORT ?? 8011);

export const appURL = `http://127.0.0.1:${appPort}`;
export const setupURL = `http://127.0.0.1:${setupPort}`;

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './test-results',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : 3,
    reporter: process.env.CI
        ? [['github'], ['list'], ['html', { open: 'never' }]]
        : [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: appURL,
        locale: 'en-US',
        timezoneId: 'UTC',
        // What helps to debug a failed test: on CI its retry is recorded (a trace, to replay it step by step with the
        // page, the network and the console, and a video), so that the tests that pass are not slowed down; locally
        // the trace of every failed test is kept
        trace: process.env.CI ? 'on-first-retry' : 'retain-on-failure',
        video: process.env.CI ? 'on-first-retry' : 'off',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'auth-setup',
            testMatch: /auth\.setup\.ts/,
        },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            testIgnore: [/global-state\//, /setup-wizard\.spec\.ts/, /\.setup\.ts/],
            dependencies: ['auth-setup'],
        },
        {
            // The tests that change what the whole application does (settings, root user): one at a time, after the
            // others, so that they never change it under their feet
            name: 'global-state',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /global-state\/.*\.spec\.ts/,
            workers: 1,
            dependencies: ['chromium', 'mobile'],
        },
        {
            name: 'mobile',
            use: { ...devices['Pixel 7'] },
            grep: /@mobile/,
            testIgnore: [/global-state\//, /setup-wizard\.spec\.ts/, /\.setup\.ts/],
            dependencies: ['auth-setup'],
        },
        {
            name: 'setup-wizard',
            use: { ...devices['Desktop Chrome'], baseURL: setupURL },
            testMatch: /setup-wizard\.spec\.ts/,
            workers: 1,
        },
    ],
    webServer: [
        {
            command: `tests/e2e/support/serve.sh ${appInstance} ${appPort}`,
            url: `${appURL}/up`,
            reuseExistingServer: !process.env.CI,
            timeout: 180_000,
            stdout: 'ignore',
            stderr: 'pipe',
        },
        {
            command: `tests/e2e/support/serve.sh setup ${setupPort}`,
            url: `${setupURL}/up`,
            reuseExistingServer: !process.env.CI,
            timeout: 180_000,
            stdout: 'ignore',
            stderr: 'pipe',
        },
    ],
});
