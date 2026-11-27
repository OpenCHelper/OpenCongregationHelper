import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';
import { php } from './support/artisan';
import { appInstance } from '../../playwright.config';

/**
 * The token of the Artisan endpoints, kept in the storage of the seeded instance (see `EnsureArtisanToken`). The same one
 * is written by every run, and never removed, so that the runs in parallel never pull it from under each other: the
 * storage is emptied when `serve.sh` starts anyway.
 */
const artisanToken = 'e2e-artisan-token';
const artisanTokenFile = path.join(
    fileURLToPath(new URL('../..', import.meta.url)),
    'storage',
    'e2e',
    appInstance,
    'framework',
    '.artisan-token',
);

/**
 * The token of the cron endpoints (`ARTISAN_CRON_TOKEN` in `serve.sh`).
 */
const cronToken = 'e2e-cron-token';

/**
 * Headers making a request come from an address of its own: the cron endpoints are limited to 10 requests a minute by
 * address, and the tests in parallel all come from 127.0.0.1.
 */
const ownAddress = (): Record<string, string> => ({
    'X-Forwarded-For': `10.${Math.floor(Math.random() * 256)}.${Math.floor(Math.random() * 256)}.${1 + Math.floor(Math.random() * 254)}`,
    Accept: 'application/json',
});

/**
 * Store the token of the Artisan endpoints, unless it already is.
 */
function setUpArtisanToken(): void {
    if (!existsSync(artisanTokenFile) || readFileSync(artisanTokenFile, 'utf8').trim() !== artisanToken) {
        writeFileSync(artisanTokenFile, `${artisanToken}\n`);
    }
}

test.describe('Artisan command endpoints', () => {
    test.beforeAll(() => setUpArtisanToken());

    test('reject the requests without a token', async ({ request }) => {
        const list = await request.get('/api/artisan/list', { headers: ownAddress() });
        expect(list.status()).toBe(401);
        expect(await list.json()).toEqual({ error: 'Unauthorized' });

        const execute = await request.post('/api/artisan/execute/about', { headers: ownAddress() });
        expect(execute.status()).toBe(401);
    });

    test('reject the requests with a wrong token, or with the old bypass header', async ({ request }) => {
        const variants: Record<string, string>[] = [{ 'X-Artisan-Token': 'wrong' }, { 'X-Artisan-Token-Bypass': 'true' }, { 'X-Artisan-Token': '' }];

        for (const headers of variants) {
            const list = await request.get('/api/artisan/list', { headers: { ...ownAddress(), ...headers } });
            expect(list.status(), JSON.stringify(headers)).toBe(401);

            const execute = await request.post('/api/artisan/execute/test-endpoint', { headers: { ...ownAddress(), ...headers } });
            expect(execute.status(), JSON.stringify(headers)).toBe(401);
        }
    });

    test('list the commands with the right token', async ({ request }) => {
        const response = await request.get('/api/artisan/list', { headers: { ...ownAddress(), 'X-Artisan-Token': artisanToken } });

        expect(response.status()).toBe(200);
        const body = await response.json();
        expect(body.success).toBe(true);
        expect(body.output).toEqual(expect.arrayContaining(['about', 'migrate', 'queue:work', 'schedule:run']));
    });

    test('execute a command with the right token, and refuse an unknown one', async ({ request }) => {
        const headers = { ...ownAddress(), 'X-Artisan-Token': artisanToken };

        const ping = await request.post('/api/artisan/execute/test-endpoint', { headers, data: { params: '--e2e' } });
        expect(ping.status()).toBe(200);
        expect(await ping.json()).toMatchObject({ success: true, returnCode: 0 });
        expect((await ping.json()).output).toContain('>>> --e2e <<<');

        const about = await request.post('/api/artisan/execute/about', { headers, data: { params: '--only=environment' } });
        expect(about.status()).toBe(200);
        expect(await about.json()).toMatchObject({ success: true, returnCode: 0 });
        expect((await about.json()).output).toContain('OCH E2E');

        const unknown = await request.post('/api/artisan/execute/e2e:no-such-command', { headers });
        expect(unknown.status()).toBe(404);
        expect(await unknown.json()).toMatchObject({ success: false, returnCode: -1 });

        const invalid = await request.post('/api/artisan/execute/about', { headers, data: { params: ['--json'] } });
        expect(invalid.status()).toBe(422);
    });
});

test.describe('Cron endpoints', () => {
    for (const endpoint of ['/api/artisan/queue/process', '/api/artisan/schedule/process']) {
        test(`${endpoint} rejects a missing or wrong X-Cron-Token`, async ({ request }) => {
            const missing = await request.post(endpoint, { headers: ownAddress() });
            expect(missing.status()).toBe(401);
            expect(await missing.json()).toEqual({ error: 'Unauthorized' });

            const wrong = await request.post(endpoint, { headers: { ...ownAddress(), 'X-Cron-Token': 'wrong' } });
            expect(wrong.status()).toBe(401);

            // The token of the Artisan commands is not the one of the cron endpoints
            const artisan = await request.post(endpoint, { headers: { ...ownAddress(), 'X-Artisan-Token': artisanToken } });
            expect(artisan.status()).toBe(401);
        });

        test(`${endpoint} runs with the right X-Cron-Token`, async ({ request }) => {
            const response = await request.post(endpoint, { headers: { ...ownAddress(), 'X-Cron-Token': cronToken } });

            expect(response.status()).toBe(200);
            expect(await response.json()).toMatchObject({ success: true, returnCode: 0 });
        });
    }

    test('the queue endpoint refuses parameters out of its limits', async ({ request }) => {
        const response = await request.post('/api/artisan/queue/process', {
            headers: { ...ownAddress(), 'X-Cron-Token': cronToken },
            data: { maxJobs: 1000 },
        });

        expect(response.status()).toBe(400);
        expect(await response.json()).toMatchObject({ success: false, output: 'Invalid max_jobs parameter' });
    });
});

test.describe('MCP server of the AI assistants', () => {
    test.beforeAll(() => {
        // The tests expect the default of the Security settings, which they never change
        const enabled = php<boolean>('return app(\\App\\Settings\\SecuritySettings::class)->ai_assistants_enabled;');
        test.skip(enabled, 'The AI assistants have been allowed in the Security settings.');
    });

    test('is not served while the AI assistants are not allowed', async ({ request }) => {
        const initialize = await request.post('/mcp', {
            headers: ownAddress(),
            data: { jsonrpc: '2.0', id: 1, method: 'initialize', params: {} },
        });
        expect(initialize.status()).toBe(404);

        const withToken = await request.post('/mcp', {
            headers: { ...ownAddress(), Authorization: 'Bearer not-a-token' },
            data: { jsonrpc: '2.0', id: 1, method: 'tools/list', params: {} },
        });
        expect(withToken.status()).toBe(404);
        expect(withToken.headers()['www-authenticate']).toBeUndefined();
    });

    test('hides the OAuth discovery and registration endpoints while the AI assistants are not allowed', async ({ request }) => {
        for (const url of ['/.well-known/oauth-protected-resource/mcp', '/.well-known/oauth-protected-resource', '/.well-known/oauth-authorization-server']) {
            const response = await request.get(url, { headers: ownAddress() });
            expect(response.status(), url).toBe(404);
        }

        const register = await request.post('/oauth/register', {
            headers: ownAddress(),
            data: { client_name: 'E2E', redirect_uris: ['https://claude.ai/api/mcp/auth_callback'] },
        });
        expect(register.status()).toBe(404);
        expect(php<number>("return \\Laravel\\Passport\\Client::query()->where('name', 'E2E')->count();")).toBe(0);
    });
});
