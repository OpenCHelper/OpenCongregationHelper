import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../..', import.meta.url));

/**
 * The environment of an instance served by `serve.sh`, so that the commands use its `.env`, storage and database.
 */
type Instance = 'app' | 'setup';

/**
 * The directory name of an instance (the seeded one can be renamed with `E2E_APP_INSTANCE`, see `playwright.config.ts`).
 */
const nameOf = (instance: Instance): string => (instance === 'app' ? (process.env.E2E_APP_INSTANCE ?? 'app') : instance);

const environmentOf = (instance: Instance): NodeJS.ProcessEnv => ({
    ...process.env,
    APP_ENV: `e2e-${nameOf(instance)}`,
    LARAVEL_STORAGE_PATH: path.join(root, 'storage', 'e2e', nameOf(instance)),
    // Its own caches, as in `serve.sh`: a cached development configuration would skip its `.env`
    APP_CONFIG_CACHE: path.join(root, 'storage', 'e2e', nameOf(instance), 'framework', 'cache', 'config.php'),
    APP_ROUTES_CACHE: path.join(root, 'storage', 'e2e', nameOf(instance), 'framework', 'cache', 'routes.php'),
    APP_EVENTS_CACHE: path.join(root, 'storage', 'e2e', nameOf(instance), 'framework', 'cache', 'events.php'),
});

/**
 * Run an Artisan command on an instance of the application, returning its output.
 */
export function artisan(args: string[], instance: Instance = 'app'): string {
    try {
        return execFileSync('php', ['artisan', ...args, '--no-interaction'], {
            cwd: root,
            env: environmentOf(instance),
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        });
    } catch (error) {
        const { stdout, stderr } = error as { stdout?: string; stderr?: string };

        throw new Error(`php artisan ${args[0]} failed:\n${[stdout, stderr].filter(Boolean).join('\n').trim()}`);
    }
}

/**
 * Run PHP code in the application (through Tinker) and return what it prints as JSON: e.g. to arrange data, or to
 * read what the pages don't show (a link sent by email).
 */
export function php<T = unknown>(code: string, instance: Instance = 'app'): T {
    const marker = '__E2E_RESULT__';
    const output = artisan(['tinker', '--execute', `echo '${marker}'.json_encode((function () { ${code} })());`], instance);
    const json = output.slice(output.lastIndexOf(marker) + marker.length).trim();

    return JSON.parse(json) as T;
}
