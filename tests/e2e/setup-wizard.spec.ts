import path from 'node:path';
import { expect, type Page, test } from '@playwright/test';
import { artisan, php } from './support/artisan';
import { useOwnAddress } from './support/fixtures';
import { choose, field, settle } from './support/filament';

/**
 * The setup wizard, on the `setup` instance: an empty installation (without a database configured) that the wizard
 * installs, with the database of the run (`E2E_DB_CONNECTION`, see `serve.sh`).
 *
 * The installation can only be done once: the tests run in order, and the last ones check the installed application.
 */
test.describe.configure({ mode: 'serial' });

const token = 'e2e-setup-token';
const root = { firstName: 'Wizard', lastName: 'Root', email: 'wizard-root@e2e.test', password: 'WizardPassword123' };
const administrator = { firstName: 'Second', lastName: 'Admin', email: 'second-admin@e2e.test' };
const congregation = 'E2E Wizard Congregation';

const connection = process.env.E2E_DB_CONNECTION ?? 'sqlite';
const connectionNames: Record<string, string> = { sqlite: 'SQLite', mysql: 'MySQL', mariadb: 'MariaDB', pgsql: 'PostgreSQL' };

/**
 * The database the wizard configures: the one of the instance (see `serve.sh`).
 */
const database = connection === 'sqlite'
    ? { database: path.resolve('storage/e2e/setup/database.sqlite') }
    : {
          database: 'e2e_setup',
          host: process.env.E2E_DB_HOST ?? '127.0.0.1',
          port: process.env.E2E_DB_PORT ?? (connection === 'pgsql' ? '5432' : '3306'),
          username: process.env.E2E_DB_USERNAME ?? 'root',
          password: process.env.E2E_DB_PASSWORD ?? '',
      };

const step = (page: Page, name: string) => page.getByRole('group', { name: new RegExp(`^${name} Step`) });

async function next(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Next', exact: true }).filter({ visible: true }).click();
    await settle(page);
}

async function unlock(page: Page, value: string = token): Promise<void> {
    await page.goto('/setup');
    await settle(page);
    await field(page, 'Setup token').fill(value);
    await page.getByRole('button', { name: 'Continue' }).click();
    await settle(page);
}

test.beforeEach(async ({ page }) => {
    // The token is rate limited by address
    await useOwnAddress(page);
});

test('every page opens the wizard while the app is not installed', async ({ page }) => {
    for (const url of ['/', '/app', '/app/login', '/app/territories']) {
        await page.goto(url);
        await expect(page, url).toHaveURL(/\/setup$/);
    }

    await expect(page.getByRole('heading', { name: 'Install the app' })).toBeVisible();
});

test('the wizard stays locked with a wrong token', async ({ page }) => {
    await unlock(page, 'not-the-token');

    await expect(field(page, 'Setup token')).toBeVisible();
    await expect(page.getByText('The setup token is not correct.')).toBeVisible();
    await expect(page.getByRole('button', { name: /Step 1 of 9/ })).toHaveCount(0);
});

test('the wizard installs the app step by step', async ({ page }) => {
    await unlock(page);

    // 1. Requirements
    await expect(step(page, 'Server')).toContainText('PHP version');
    await next(page);

    // 2. Configuration: the database is the only missing part of `.env`
    const configuration = step(page, 'Configuration');
    await expect(configuration).toBeVisible();
    await choose(configuration, 'DB_CONNECTION', connectionNames[connection]);
    await settle(page);
    await field(configuration, 'DB_DATABASE').fill(database.database);

    if (connection !== 'sqlite') {
        await field(configuration, 'DB_HOST').fill(database.host!);
        await field(configuration, 'DB_PORT').fill(database.port!);
        await field(configuration, 'DB_USERNAME').fill(database.username!);
        await field(configuration, 'DB_PASSWORD').fill(database.password!);
    }

    await next(page);

    // 3. Background tasks
    await expect(step(page, 'Background tasks')).toBeVisible();
    await next(page);

    // 4. Database: the tables are created
    await expect(step(page, 'Database')).toBeVisible();
    await next(page);

    // 5. Root user
    const rootStep = step(page, 'Root user');
    await field(rootStep, 'First Name').fill(root.firstName);
    await field(rootStep, 'Last Name').fill(root.lastName);
    await field(rootStep, 'Email').fill(root.email);
    await field(rootStep, 'Password').fill(root.password);
    await field(rootStep, 'Confirm the password').fill(root.password);
    await next(page);

    // 6. Congregation
    const congregationStep = step(page, 'Congregation');
    await expect(congregationStep).toBeVisible();
    await congregationStep.getByRole('textbox').first().fill(congregation);
    await next(page);

    // 7. Administrators
    const administratorsStep = step(page, 'Administrators');
    await expect(administratorsStep).toBeVisible();
    await administratorsStep.getByRole('button', { name: /add/i }).first().click();
    await field(administratorsStep, 'First Name').fill(administrator.firstName);
    await field(administratorsStep, 'Last Name').fill(administrator.lastName);
    await field(administratorsStep, 'Email').fill(administrator.email);
    await next(page);

    // 8. Features
    await expect(step(page, 'Features')).toBeVisible();
    await next(page);

    // 9. Review, then install
    const review = step(page, 'Review');
    await expect(review).toContainText(root.email);
    await expect(review).toContainText(congregation);
    await page.getByRole('button', { name: 'Install', exact: true }).filter({ visible: true }).click();
    await settle(page);

    await expect(page.getByRole('heading', { name: 'The app is installed' })).toBeVisible({ timeout: 60_000 });
    await expect(page.getByText(`Root user created: ${root.email}`)).toBeVisible();
});

test('the installed app has the root user, the congregation and the administrator', async () => {
    const installed = php<{ root: string | null; superAdmin: boolean; name: string | null; admins: string[] }>(`
        $root = \\App\\Models\\User::rootUser();

        return [
            'root' => $root?->email,
            'superAdmin' => (bool) $root?->hasRole('super_admin'),
            'name' => app(\\App\\Settings\\GeneralSettings::class)->app_name,
            'admins' => \\App\\Models\\User::role('super_admin')->pluck('email')->all(),
        ];
    `, 'setup');

    expect(installed.root).toBe(root.email);
    expect(installed.superAdmin).toBe(true);
    expect(installed.name).toBe(congregation);
    expect(installed.admins).toContain(administrator.email);
    expect(artisan(['migrate:status'], 'setup')).not.toContain('Pending');
});

test('the root user logs in to the installed app', async ({ page }) => {
    await page.goto('/app/login');
    await settle(page);
    await field(page, 'Email address').fill(root.email);
    await field(page, 'Password').fill(root.password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/app\/?$/);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await expect(page.getByRole('link', { name: congregation }).first()).toBeVisible();
});

test('the wizard is no longer available once installed', async ({ page }) => {
    const response = await page.goto('/setup');

    expect(response?.status()).toBe(403);
});
