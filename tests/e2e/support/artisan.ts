import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../..', import.meta.url));

/**
 * The environment of an instance served by `serve.sh`, so that the commands use its `.env`, storage and database.
 */
const environmentOf = (instance: 'app' | 'setup'): NodeJS.ProcessEnv => ({
    ...process.env,
    APP_ENV: `e2e-${instance}`,
    LARAVEL_STORAGE_PATH: path.join(root, 'storage', 'e2e', instance),
});

/**
 * Run an Artisan command on an instance of the application, returning its output.
 */
export function artisan(args: string[], instance: 'app' | 'setup' = 'app'): string {
    return execFileSync('php', ['artisan', ...args, '--no-interaction'], {
        cwd: root,
        env: environmentOf(instance),
        encoding: 'utf8',
    });
}

/**
 * Run PHP code in the application (through Tinker) and return what it prints as JSON: e.g. to arrange data, or to
 * read what the pages don't show (a link sent by email).
 */
export function php<T = unknown>(code: string, instance: 'app' | 'setup' = 'app'): T {
    const marker = '__E2E_RESULT__';
    const output = artisan(['tinker', '--execute', `echo '${marker}'.json_encode((function () { ${code} })());`], instance);
    const json = output.slice(output.lastIndexOf(marker) + marker.length).trim();

    return JSON.parse(json) as T;
}
