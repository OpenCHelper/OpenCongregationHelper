import type { Browser, Locator, Page } from '@playwright/test';
import { appURL } from '../../playwright.config';
import { test, expect, logIn, uniqueName } from './support/fixtures';
import { php } from './support/artisan';
import { confirmModal, expectNotification, field, labelled, modal, row, rowAction, searchTable, settle, visit } from './support/filament';

/**
 * A name of role nobody else uses, in the `snake_case` of the seeded ones (shown as a headline, e.g. "E2e Reader Abc").
 */
const uniqueRoleName = (): string => uniqueName('e2e reader').toLowerCase().replaceAll(' ', '_');

/**
 * A throwaway user who can log in (with the password `password`), without a role.
 */
function createUser(): { id: number; email: string; firstName: string } {
    const firstName = uniqueName('Roled');
    const email = `${firstName.toLowerCase().replaceAll(' ', '-')}@e2e.test`;
    const id = php<number>(`
        return \\App\\Models\\User::factory()->verified()->create([
            'first_name' => ${JSON.stringify(firstName)},
            'last_name' => 'Tester',
            'email' => ${JSON.stringify(email)},
            'locale' => 'en',
        ])->id;
    `);

    return { id, email, firstName };
}

/**
 * Create a role with the permissions, in the database.
 */
function createRole(name: string, permissions: string[]): number {
    return php<number>(`
        $role = \\Spatie\\Permission\\Models\\Role::create(['name' => ${JSON.stringify(name)}, 'guard_name' => 'web']);
        $role->syncPermissions(${JSON.stringify(permissions)});

        return $role->id;
    `);
}

/**
 * The permissions of a role, sorted; `null` when the role does not exist.
 */
const permissionsOf = (name: string): string[] | null => php<string[] | null>(`
    $role = \\Spatie\\Permission\\Models\\Role::where('name', ${JSON.stringify(name)})->first();

    return $role?->permissions->pluck('name')->sort()->values()->all();
`);

/**
 * The group of checkboxes of the permissions of a resource, in the role form.
 */
const permissionsOfResource = (page: Page, resource: string): Locator =>
    page.getByRole('region', { name: resource, exact: true }).getByRole('group');

/**
 * Sign in as the user in a browser context of their own.
 */
async function pageOf(browser: Browser, email: string): Promise<Page> {
    const page = await (await browser.newContext({ baseURL: appURL })).newPage();
    await logIn(page, email, 'password');
    await expect(page).not.toHaveURL(/\/login/);

    return page;
}

/**
 * The HTTP status of a page for the user of the page (without following the redirects).
 */
async function statusOf(page: Page, url: string): Promise<number> {
    return (await page.context().request.get(url, { maxRedirects: 0 })).status();
}

const sidebarLink = (page: Page, name: string): Locator =>
    page.getByRole('navigation', { name: 'Sidebar navigation' }).getByRole('link', { name: new RegExp(`^${name}(\\s+\\d+)?$`) });

test.describe('Roles', () => {
    test('create a role reading the territories, give it to a user, who sees only the territories', async ({ adminPage, browser }) => {
        const roleName = uniqueRoleName();
        const user = createUser();

        await visit(adminPage, '/app/shield/roles');
        await adminPage.getByRole('link', { name: 'New Role' }).click();
        await expect(adminPage.getByRole('heading', { name: 'Create Role', level: 1 })).toBeVisible();

        await field(adminPage, 'Name').fill(roleName);
        const territory = permissionsOfResource(adminPage, 'Territory');
        await territory.getByRole('checkbox', { name: 'View Any', exact: true }).check();
        await territory.getByRole('checkbox', { name: 'View', exact: true }).check();
        await adminPage.getByRole('button', { name: 'Create', exact: true }).click();
        await expectNotification(adminPage, 'Created');

        expect(permissionsOf(roleName)).toEqual(['View:Territory', 'ViewAny:Territory']);

        await visit(adminPage, '/app/shield/roles');
        await searchTable(adminPage, roleName);
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        await expect(adminPage.locator('.fi-ta-row').first()).toContainText('2');

        // Give the role to the user from their page
        await visit(adminPage, `/app/users/${user.id}/edit`);
        await adminPage.locator('.fi-fo-field').filter({ has: adminPage.locator('label', { hasText: labelled('Roles') }) }).locator('.fi-select-input-btn').click();
        await adminPage.locator('.fi-select-input-search-ctn input').filter({ visible: true }).first().fill(roleName);
        await adminPage.getByRole('option', { name: roleName }).filter({ visible: true }).first().click();
        await adminPage.keyboard.press('Escape');
        await adminPage.getByRole('button', { name: 'Save changes' }).click();
        await expectNotification(adminPage, 'Saved');
        expect(php<string[]>(`return \\App\\Models\\User::find(${user.id})->getRoleNames()->all();`)).toEqual([roleName]);

        const userPage = await pageOf(browser, user.email);
        await visit(userPage, '/app');
        await expect(sidebarLink(userPage, 'Territories')).toBeVisible();
        for (const item of ['Users', 'Zones', 'Campaigns', 'Assignments', 'Roles']) {
            await expect(sidebarLink(userPage, item), item).toHaveCount(0);
        }

        await visit(userPage, '/app/territories');
        await expect(userPage.getByRole('heading', { name: 'Territories', level: 1 })).toBeVisible();
        await expect(userPage.locator('.fi-header').getByRole('link', { name: 'New territory' })).toHaveCount(0);

        expect(await statusOf(userPage, '/app/users')).toBe(403);
        expect(await statusOf(userPage, '/app/territories/create')).toBe(403);

        await userPage.context().close();
    });

    test('editing the permissions of a role changes what its users reach', async ({ adminPage, browser }) => {
        const roleName = uniqueRoleName();
        const roleId = createRole(roleName, ['ViewAny:Territory', 'View:Territory']);
        const user = createUser();
        php(`\\App\\Models\\User::find(${user.id})->assignRole(${JSON.stringify(roleName)}); return null;`);

        const userPage = await pageOf(browser, user.email);
        expect(await statusOf(userPage, '/app/users')).toBe(403);

        await visit(adminPage, `/app/shield/roles/${roleId}/edit`);
        const territory = permissionsOfResource(adminPage, 'Territory');
        await expect(territory.getByRole('checkbox', { name: 'View Any', exact: true })).toBeChecked();
        await expect(territory.getByRole('checkbox', { name: 'Create', exact: true })).not.toBeChecked();

        // Read the users, no longer the territories
        const users = permissionsOfResource(adminPage, 'User');
        await users.getByRole('checkbox', { name: 'View Any', exact: true }).check();
        await users.getByRole('checkbox', { name: 'View', exact: true }).check();
        await territory.getByRole('checkbox', { name: 'View Any', exact: true }).uncheck();
        await territory.getByRole('checkbox', { name: 'View', exact: true }).uncheck();
        await adminPage.getByRole('button', { name: 'Save changes' }).click();
        await expectNotification(adminPage, 'Saved');

        expect(permissionsOf(roleName)).toEqual(['View:User', 'ViewAny:User']);

        await visit(userPage, '/app');
        await expect(sidebarLink(userPage, 'Users')).toBeVisible();
        await expect(sidebarLink(userPage, 'Territories')).toHaveCount(0);
        expect(await statusOf(userPage, '/app/users')).toBe(200);
        expect(await statusOf(userPage, '/app/territories')).toBe(403);

        await userPage.context().close();
    });

    test('delete a role, whose users lose its permissions', async ({ adminPage, browser }) => {
        const roleName = uniqueRoleName();
        createRole(roleName, ['ViewAny:Territory', 'View:Territory']);
        const user = createUser();
        php(`\\App\\Models\\User::find(${user.id})->assignRole(${JSON.stringify(roleName)}); return null;`);

        await visit(adminPage, '/app/shield/roles');
        await searchTable(adminPage, roleName);
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        const roleRow = (await adminPage.locator('.fi-ta-row').first().innerText()).split('\n')[0].trim();
        await rowAction(adminPage, roleRow, 'Delete');
        await confirmModal(adminPage, 'Delete');
        await expectNotification(adminPage, 'Deleted');
        await settle(adminPage);

        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(0);
        expect(permissionsOf(roleName)).toBeNull();
        expect(php<string[]>(`return \\App\\Models\\User::find(${user.id})->getRoleNames()->all();`)).toEqual([]);

        const userPage = await pageOf(browser, user.email);
        expect(await statusOf(userPage, '/app/territories')).toBe(403);
        await userPage.context().close();
    });

    test('the super admin role cannot be deleted', async ({ adminPage }) => {
        await visit(adminPage, '/app/shield/roles');

        const superAdmin = row(adminPage, 'Super Admin');
        await expect(superAdmin.getByRole('link', { name: 'Edit' })).toBeVisible();
        await expect(superAdmin.getByRole('button', { name: 'Delete' })).toHaveCount(0);
        await expect(superAdmin.getByRole('checkbox')).toHaveCount(0);

        await superAdmin.getByRole('link', { name: 'Edit' }).click();
        await expect(adminPage.getByRole('heading', { name: /^Edit/, level: 1 })).toBeVisible();
        await expect(adminPage.locator('.fi-header').getByRole('button', { name: 'Delete' })).toHaveCount(0);
        await expect(modal(adminPage)).toHaveCount(0);
    });
});
