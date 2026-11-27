import { test, expect } from './support/fixtures';

/**
 * The public pages, reachable without signing in: the welcome page, the redirects of the old routes, the files of the
 * Progressive Web App and the error pages.
 */
test.describe('Welcome page', () => {
    test('invites guests to log in and links to the knowledge base @mobile', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByRole('heading', { level: 1, name: 'The territory register, organized and always at hand.' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Current features' })).toBeVisible();
        await expect(page.getByRole('main').getByRole('link', { name: 'Log in' })).toHaveAttribute('href', /\/app\/login$/);
        await expect(page.getByRole('main').getByRole('link', { name: 'Open the app' })).toHaveCount(0);

        await page.getByRole('link', { name: 'Read the guide' }).click();
        await expect(page).toHaveURL(/\/knowledge-base/);
    });

    test('leads guests to the login page', async ({ page }) => {
        await page.goto('/');
        await page.getByRole('main').getByRole('link', { name: 'Log in' }).click();

        await expect(page).toHaveURL(/\/app\/login$/);
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    });

    test('links signed-in users to the dashboard', async ({ adminPage }) => {
        await adminPage.goto('/');

        const openApp = adminPage.getByRole('main').getByRole('link', { name: 'Open the app' });
        await expect(openApp).toHaveAttribute('href', /\/app$/);
        await expect(adminPage.getByRole('link', { name: 'Log in' })).toHaveCount(0);

        await openApp.click();
        await expect(adminPage).toHaveURL(/\/app$/);
        await expect(adminPage.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    });
});

test.describe('Redirects', () => {
    test('sends /login to the login page of the panel', async ({ page }) => {
        await page.goto('/login');

        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('sends /dashboard to the panel, and guests on to the login page', async ({ page }) => {
        const response = await page.request.get('/dashboard', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        expect(response.headers().location).toMatch(/\/app$/);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('sends signed-in users from /dashboard to the dashboard', async ({ adminPage }) => {
        await adminPage.goto('/dashboard');

        await expect(adminPage).toHaveURL(/\/app$/);
        await expect(adminPage.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    });

    test('sends /user/profile to the profile page', async ({ adminPage }) => {
        await adminPage.goto('/user/profile');

        await expect(adminPage).toHaveURL(/\/app\/edit-profile$/);
    });
});

test.describe('Progressive Web App', () => {
    test('serves the web app manifest with the icons', async ({ request }) => {
        const response = await request.get('/manifest.webmanifest');

        expect(response.ok()).toBe(true);
        expect(response.headers()['content-type']).toContain('application/manifest+json');

        const manifest = await response.json();
        expect(manifest.name).toBeTruthy();
        expect(manifest.start_url).toBe('/app');
        expect(manifest.display).toBe('standalone');

        const sizes = manifest.icons.map((icon: { sizes: string }) => icon.sizes);
        expect(sizes).toEqual(expect.arrayContaining(['192x192', '512x512']));

        const icon = await request.get(manifest.icons.find((each: { sizes: string }) => each.sizes === '192x192').src);
        expect(icon.ok()).toBe(true);
        expect(icon.headers()['content-type']).toContain('image/png');
    });

    test('links the manifest from the pages', async ({ page }) => {
        await page.goto('/app/login');

        await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', /\/manifest\.webmanifest$/);
    });

    test('serves the offline page', async ({ page }) => {
        await page.goto('/offline');

        await expect(page.getByRole('heading', { name: 'You are offline' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible();
    });

    test('shows the offline page without a connection, then the page once it is back @mobile', async ({ publisherPage: page, browserName }) => {
        test.skip(browserName === 'webkit', "Playwright's WebKit fails the requests offline before the service worker gets them, even the cached ones.");

        await page.goto('/app');
        // The service worker (`public/sw.js`) caches the offline page when it installs, and takes over the open page
        await expect.poll(() => page.evaluate(async () => {
            await navigator.serviceWorker.ready;

            return navigator.serviceWorker.controller !== null;
        })).toBe(true);

        await page.context().setOffline(true);
        await page.goto('/app/territories');

        await expect(page.getByRole('heading', { level: 1, name: 'You are offline' })).toBeVisible();
        await expect(page).toHaveURL(/\/app\/territories$/);

        await page.context().setOffline(false);
        await page.getByRole('button', { name: 'Try again' }).click();

        await expect(page.getByRole('heading', { level: 1, name: 'Territories' })).toBeVisible();
    });
});

test.describe('Error pages and headers', () => {
    test('answers 404 to unknown pages', async ({ page }) => {
        const response = await page.goto('/this-page-does-not-exist');

        expect(response?.status()).toBe(404);
        await expect(page.getByText(/not found/i).first()).toBeVisible();
    });

    for (const [description, url] of [
        ['web pages', '/app/login'],
        ['redirects', '/dashboard'],
        ['error pages', '/this-page-does-not-exist'],
        ['the API', '/api/artisan/list'],
    ] as const) {
        test(`sends the security headers on ${description}`, async ({ request }) => {
            const response = await request.get(url, { maxRedirects: 0, headers: { Accept: url.startsWith('/api') ? 'application/json' : 'text/html' } });
            const headers = response.headers();

            expect(headers['x-frame-options']).toBe('DENY');
            expect(headers['x-content-type-options']).toBe('nosniff');
            expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
            expect(headers['x-powered-by']).toBeUndefined();
        });
    }
});
