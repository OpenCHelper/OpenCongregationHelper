import type { Browser, Locator, Page } from '@playwright/test';
import { appURL } from '../../playwright.config';
import { test, expect, logIn, uniqueName, users } from './support/fixtures';
import { php } from './support/artisan';
import { confirmModal, expectNotification, field, labelled, modal, row, rowAction, searchTable, settle, visit } from './support/filament';
import { lastMailTo, linkInLastMailTo } from './support/mail';

type ThrowawayUser = { id: number; firstName: string; lastName: string; name: string; email: string };

/**
 * A throwaway user (with the password `password`, verified, a publisher), with the attributes given.
 */
function createUser(attributes: Record<string, unknown> = {}, role: string | null = 'publisher', lastName = 'Throwaway'): ThrowawayUser {
    const firstName = uniqueName('User');
    const email = `${firstName.toLowerCase().replaceAll(' ', '-')}@e2e.test`;
    const id = php<number>(`
        $user = \\App\\Models\\User::factory()->verified()->create(array_merge([
            'first_name' => ${JSON.stringify(firstName)},
            'last_name' => ${JSON.stringify(lastName)},
            'email' => ${JSON.stringify(email)},
            'locale' => 'en',
        ], json_decode(${JSON.stringify(JSON.stringify(attributes))}, true)));
        ${role ? `$user->assignRole(${JSON.stringify(role)});` : ''}

        return $user->id;
    `);

    return { id, firstName, lastName, name: `${firstName} ${lastName}`, email };
}

/**
 * The attributes of a user in the database, `null` once deleted.
 */
const userInDatabase = (id: number): { first_name: string; last_name: string; can_login: boolean; roles: string[]; departments: string[] } | null =>
    php(`
        $user = \\App\\Models\\User::find(${id});

        return $user ? [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'can_login' => (bool) $user->can_login,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'departments' => $user->departments->map(fn ($department) => $department->key->value . ':' . (is_string($department->pivot->role) ? $department->pivot->role : $department->pivot->role->value))->all(),
        ] : null;
    `);

/**
 * Pick an option of a searchable select of Filament (a button named after its label, then after the options chosen).
 */
async function pickOption(page: Page, scope: Page | Locator, label: string, option: string, close: () => Promise<void> = () => page.keyboard.press('Escape')): Promise<void> {
    const search = page.locator('.fi-select-input-search-ctn input').filter({ visible: true }).first();

    if (!(await search.isVisible())) {
        await scope.locator('.fi-fo-field').filter({ has: page.locator('label', { hasText: labelled(label) }) }).locator('.fi-select-input-btn').click();
    }

    // The selects with few options have no search
    const choice = page.getByRole('option', { name: option, exact: true }).filter({ visible: true }).first();
    await expect(search.or(choice).first()).toBeVisible();

    if (await search.isVisible()) {
        await search.fill(option);
    }

    await choice.click();
    await close();
}

/**
 * Run a bulk action of the table on the rows: the actions of the group are menu items.
 */
async function bulkMenuAction(page: Page, rowTexts: string[], action: string): Promise<void> {
    for (const text of rowTexts) {
        await row(page, text).getByRole('checkbox').check();
    }

    await page.getByRole('button', { name: 'Bulk actions' }).click();
    await page.getByRole('menu', { name: 'Bulk actions' }).getByRole('menuitem', { name: action }).click();
}

/**
 * Open the filters of the table, unless they still are.
 */
async function openFilters(page: Page): Promise<Locator> {
    const panel = page.locator('.fi-dropdown-panel').filter({ visible: true }).filter({ has: page.getByRole('heading', { name: 'Filters' }) });

    if (!(await panel.isVisible())) {
        await page.getByRole('button', { name: 'Filter', exact: true }).click();
    }

    await expect(panel).toBeVisible();

    return panel;
}

/**
 * Open the filters of the table, set some, and apply them.
 */
async function applyFilters(page: Page, filters: Record<string, string>): Promise<void> {
    const panel = await openFilters(page);

    for (const [label, option] of Object.entries(filters)) {
        const select = panel.getByRole('combobox', { name: label, exact: true });
        // By value: the labels have counts, that the tests in parallel change
        const value = await select.evaluate(
            (element: HTMLSelectElement, prefix: string) => [...element.options].find((choice) => choice.text.trim().startsWith(prefix))?.value ?? null,
            option,
        );
        expect(value, `${label}: ${option}`).not.toBeNull();
        await select.selectOption(value!);
    }

    await panel.getByRole('button', { name: 'Apply filters' }).click();
    await expect(panel).toBeHidden();
    await settle(page);
}

/**
 * Sign in as the super administrator in a session of their own: impersonating changes the session, that the other
 * tests share through the saved state of `adminPage`.
 */
async function ownAdminPage(browser: Browser): Promise<Page> {
    const page = await (await browser.newContext({ baseURL: appURL })).newPage();
    await logIn(page, users.admin.email, users.admin.password);
    await expect(page).not.toHaveURL(/\/login/);

    return page;
}

const sidebarLink = (page: Page, name: string): Locator =>
    page.getByRole('navigation', { name: 'Sidebar navigation' }).getByRole('link', { name: new RegExp(`^${name}(\\s+\\d+)?$`) });

test.describe('Creating users', () => {
    test('a new user is invited by email, chooses their password and signs in', async ({ adminPage, browser }) => {
        const firstName = uniqueName('Invited');
        const email = `${firstName.toLowerCase().replaceAll(' ', '-')}@e2e.test`;

        await visit(adminPage, '/app/users');
        await adminPage.getByRole('link', { name: 'New user' }).click();
        await expect(adminPage.getByRole('heading', { name: 'Create User', level: 1 })).toBeVisible();

        await field(adminPage, 'First Name').fill(firstName);
        await field(adminPage, 'Last Name').fill('Newcomer');
        await field(adminPage, 'Email').fill(email);
        // A live field: checked again if Livewire re-rendered it unchecked (the page was not ready yet)
        await expect(async () => {
            await adminPage.getByRole('checkbox', { name: 'Allow access' }).check();
            await expect(adminPage.getByText('They will receive an email to choose their password.')).toBeVisible({ timeout: 2_000 });
        }).toPass();
        await pickOption(adminPage, adminPage, 'Roles', 'publisher');
        await adminPage.getByRole('button', { name: 'Create', exact: true }).click();
        await expectNotification(adminPage, `An invitation to choose the password has been sent to ${email}.`);

        const created = php<{ id: number; password: string | null; roles: string[] }>(`
            $user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();

            return ['id' => $user->id, 'password' => $user->password, 'roles' => $user->getRoleNames()->all()];
        `);
        expect(created.password).toBeNull();
        expect(created.roles).toEqual(['publisher']);

        // Among the emails of the address: others (e.g. a new campaign) may reach it meanwhile
        expect(lastMailTo(email, 'Your OCH E2E account')).not.toBeNull();
        const invitation = linkInLastMailTo(email, /\/password-reset\/reset/);
        expect(invitation).toContain('invitation=1');

        // The invited user, in a browser of their own
        const invitedPage = await (await browser.newContext({ baseURL: appURL })).newPage();
        await invitedPage.goto(invitation);
        await expect(invitedPage.getByRole('heading', { name: 'Choose your password' })).toBeVisible();
        const password = 'Invited-Password-2026!';
        await field(invitedPage, 'Password').fill(password);
        await field(invitedPage, 'Confirm password').fill(password);
        await invitedPage.getByRole('button', { name: 'Set password' }).click();
        await expectNotification(invitedPage, 'Your password has been set. You can now sign in.');

        await logIn(invitedPage, email, password);
        await expect(invitedPage).toHaveURL(/\/app\/?$/);
        await expect(invitedPage.getByRole('button', { name: 'User menu' }).getByRole('img', { name: `Avatar of ${firstName} Newcomer` })).toBeVisible();
        await expect(sidebarLink(invitedPage, 'Territories')).toBeVisible();

        await invitedPage.context().close();

        // The invitation was used
        const laterPage = await (await browser.newContext({ baseURL: appURL })).newPage();
        await laterPage.goto(invitation);
        await field(laterPage, 'Password').fill('Another-Password-2026!');
        await field(laterPage, 'Confirm password').fill('Another-Password-2026!');
        await laterPage.getByRole('button', { name: 'Set password' }).click();
        await expect(laterPage.getByText('This invitation is no longer valid.', { exact: false }).first()).toBeVisible();
        await laterPage.context().close();
    });

    test('the invitation is sent again from the list, once a minute at most', async ({ adminPage }) => {
        const user = createUser({ password: null });

        await visit(adminPage, '/app/users');
        await searchTable(adminPage, user.email);
        await row(adminPage, user.email).getByRole('button', { name: 'Resend invitation' }).click();
        await expect(modal(adminPage).getByRole('heading', { name: 'Resend the invitation?' })).toBeVisible();
        await expect(modal(adminPage)).toContainText(user.email);
        await confirmModal(adminPage, 'Resend invitation');
        await expectNotification(adminPage, `An invitation to choose the password has been sent to ${user.email}.`);

        expect(lastMailTo(user.email, 'Your OCH E2E account')).not.toBeNull();
        const firstLink = linkInLastMailTo(user.email, /\/password-reset\/reset/);

        await row(adminPage, user.email).getByRole('button', { name: 'Resend invitation' }).click();
        await confirmModal(adminPage, 'Resend invitation');
        await expectNotification(adminPage, /An invitation has just been sent: wait \d+ seconds/);
        expect(linkInLastMailTo(user.email, /\/password-reset\/reset/)).toBe(firstLink);
    });

    test('the users who chose their password are not offered to resend the invitation', async ({ adminPage }) => {
        const user = createUser();

        await visit(adminPage, `/app/users/${user.id}`);
        await expect(adminPage.getByRole('heading', { name: `View ${user.name}` })).toBeVisible();
        await expect(adminPage.locator('.fi-header').getByRole('button', { name: 'Resend invitation' })).toHaveCount(0);
    });
});

test.describe('Editing users', () => {
    test('change the name and the roles of a user, then disable their access', async ({ adminPage }) => {
        const user = createUser();

        await visit(adminPage, `/app/users/${user.id}/edit`);
        await expect(adminPage.getByRole('heading', { name: `Edit ${user.name}` })).toBeVisible();

        const firstName = uniqueName('Renamed');
        await field(adminPage, 'First Name').fill(firstName);
        await field(adminPage, 'Last Name').fill('Edited');
        await pickOption(adminPage, adminPage, 'Roles', 'group_overseer');
        await adminPage.getByRole('button', { name: 'Remove publisher' }).click();
        await adminPage.getByRole('button', { name: 'Save changes' }).click();
        await expectNotification(adminPage, 'Saved');

        expect(userInDatabase(user.id)).toMatchObject({ first_name: firstName, last_name: 'Edited', can_login: true, roles: ['group_overseer'] });

        // A virtual user: the roles are hidden
        await adminPage.getByRole('checkbox', { name: 'Allow access' }).uncheck();
        await expect(adminPage.locator('.fi-fo-field').filter({ has: adminPage.locator('label', { hasText: labelled('Roles') }) })).toHaveCount(0);
        await adminPage.getByRole('button', { name: 'Save changes' }).click();
        await expectNotification(adminPage, 'Saved');

        expect(userInDatabase(user.id)?.can_login).toBe(false);

        await visit(adminPage, '/app/users');
        await searchTable(adminPage, firstName);
        await expect(row(adminPage, firstName)).toContainText('Edited');
        await expect(row(adminPage, firstName).getByText('Can log in')).toHaveCount(0);
    });
});

test.describe('List of users', () => {
    test('search the users and filter them by access, email verification, two-factor authentication and department', async ({ adminPage }) => {
        const lastName = uniqueName('Filtered').replaceAll(' ', '');
        const virtual = createUser({ can_login: false, email_verified_at: null }, null, lastName);
        const secured = createUser({ app_authentication_secret: 'JBSWY3DPEHPK3PXP' }, 'publisher', lastName);
        const plain = createUser({}, 'publisher', lastName);
        php(`\\App\\Models\\Department::where('key', 'publications')->first()->members()->attach(${plain.id}, ['role' => 'assistant']); return null;`);

        await visit(adminPage, '/app/users');
        await searchTable(adminPage, lastName);
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(3);

        // Search by email too
        await searchTable(adminPage, secured.email);
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        await expect(row(adminPage, secured.firstName)).toBeVisible();

        await searchTable(adminPage, lastName);
        await applyFilters(adminPage, { Access: 'Virtual' });
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        await expect(row(adminPage, virtual.firstName)).toBeVisible();
        await expect(adminPage.locator('.fi-ta-filter-indicators')).toContainText(/virtual/i);

        await applyFilters(adminPage, { Access: 'With access', 'Two-factor authentication': 'Enabled' });
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        await expect(row(adminPage, secured.firstName)).toBeVisible();

        await applyFilters(adminPage, { Access: 'All', 'Two-factor authentication': 'All', 'Email verified': 'Not verified' });
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        await expect(row(adminPage, virtual.firstName)).toBeVisible();

        await applyFilters(adminPage, { 'Email verified': 'Verified' });
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(2);

        // The department filter is a searchable select
        await applyFilters(adminPage, { 'Email verified': 'All' });
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(3);
        const panel = await openFilters(adminPage);
        await pickOption(adminPage, panel, 'Department', 'Publications', () => panel.getByRole('heading', { name: 'Filters' }).click());
        await panel.getByRole('button', { name: 'Apply filters' }).click();
        await settle(adminPage);
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);
        await expect(row(adminPage, plain.firstName)).toContainText('Publications');
    });

    test('add the selected users to a department', async ({ adminPage }) => {
        const lastName = uniqueName('Bulk').replaceAll(' ', '');
        const first = createUser({}, 'publisher', lastName);
        const second = createUser({}, 'publisher', lastName);

        await visit(adminPage, '/app/users');
        await searchTable(adminPage, lastName);
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(2);

        await bulkMenuAction(adminPage, [first.firstName, second.firstName], 'Add to department');
        const dialog = modal(adminPage);
        await expect(dialog.getByRole('heading', { name: 'Add to a department' })).toBeVisible();
        const department = dialog.getByRole('combobox', { name: labelled('Department') });
        if (await department.count()) {
            await department.selectOption({ label: 'Public Witnessing' });
        } else {
            await pickOption(adminPage, dialog, 'Department', 'Public Witnessing');
        }
        await dialog.getByRole('radiogroup').getByText('Assistant', { exact: true }).click();
        await confirmModal(adminPage, 'Add');
        await expectNotification(adminPage, '2 brothers added to Public Witnessing');

        expect(userInDatabase(first.id)?.departments).toEqual(['public_witnessing:assistant']);
        expect(userInDatabase(second.id)?.departments).toEqual(['public_witnessing:assistant']);
        await expect(row(adminPage, first.firstName)).toContainText('Public Witnessing');
    });
});

test.describe('Contacts of a user', () => {
    test('create, view, edit and delete a contact of a user', async ({ adminPage }) => {
        const user = createUser();
        const contactName = uniqueName('Contact');

        await visit(adminPage, `/app/users/${user.id}/edit`);
        // The contacts are loaded once scrolled into view
        await adminPage.getByRole('button', { name: 'Save changes' }).scrollIntoViewIfNeeded();
        await adminPage.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await expect(adminPage.getByRole('heading', { name: 'Contact information' })).toBeVisible();

        await adminPage.getByRole('button', { name: 'New Contact' }).click();
        let dialog = modal(adminPage);
        await expect(dialog.getByRole('heading', { name: 'Create Contact' })).toBeVisible();
        await field(dialog, 'Type').selectOption({ label: 'Emergency' });
        await field(dialog, 'Name').fill(contactName);
        await field(dialog, 'Phone number').fill('+39 06 1234567');
        await field(dialog, 'Address').fill('Via Roma 1, Ciampino');
        await confirmModal(adminPage, 'Create');
        await expectNotification(adminPage, 'Created');

        await expect(row(adminPage, contactName)).toContainText('Emergency');
        expect(php<number>(`return \\App\\Models\\User::find(${user.id})->contacts()->where('name', ${JSON.stringify(contactName)})->count();`)).toBe(1);

        await rowAction(adminPage, contactName, 'View');
        dialog = modal(adminPage);
        await expect(dialog).toContainText(contactName);
        await expect(dialog.getByRole('link', { name: 'Call' })).toHaveAttribute('href', 'tel:+39 06 1234567');
        const maps = dialog.getByRole('link', { name: 'Open in Maps' });
        await expect(maps).toHaveAttribute('href', 'https://www.google.com/maps/search/Via+Roma+1%2C+Ciampino');
        await expect(maps).toHaveAttribute('target', '_blank');
        await dialog.getByRole('button', { name: 'Close' }).first().click();

        const renamed = `${contactName} renamed`;
        await rowAction(adminPage, contactName, 'Edit');
        dialog = modal(adminPage);
        await field(dialog, 'Name').fill(renamed);
        await confirmModal(adminPage, 'Save changes');
        await expectNotification(adminPage, 'Saved');
        await expect(row(adminPage, renamed)).toBeVisible();

        await rowAction(adminPage, renamed, 'Delete');
        await confirmModal(adminPage, 'Delete');
        await expectNotification(adminPage, 'Deleted');
        await expect(adminPage.locator('.fi-ta-row').filter({ hasText: renamed })).toHaveCount(0);
        expect(php<number>(`return \\App\\Models\\User::find(${user.id})->contacts()->count();`)).toBe(0);
    });
});

test.describe('Impersonation', () => {
    test('the administrator impersonates a publisher from the list, then leaves', async ({ browser }) => {
        const user = createUser();
        const page = await ownAdminPage(browser);

        await visit(page, '/app/users');
        await searchTable(page, user.email);
        await row(page, user.email).getByRole('button', { name: 'Impersonate' }).click();
        await expect(modal(page).getByRole('heading', { name: 'Impersonate' })).toBeVisible();
        await confirmModal(page, 'Confirm');
        await page.waitForURL((url) => !url.pathname.startsWith('/app/users'));

        await visit(page, '/app');
        await expect(page.getByText('Impersonating user')).toBeVisible();
        await expect(page.getByRole('button', { name: 'User menu' }).getByRole('img', { name: `Avatar of ${user.name}` })).toBeVisible();
        await expect(sidebarLink(page, 'Territories')).toBeVisible();
        await expect(sidebarLink(page, 'Users')).toHaveCount(0);
        expect((await page.context().request.get('/app/users', { maxRedirects: 0 })).status()).toBe(403);

        await visit(page, '/app');
        await page.getByRole('link', { name: 'Leave' }).click();
        await settle(page);
        await visit(page, '/app');
        await expect(page.getByText('Impersonating user')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'User menu' }).getByRole('img', { name: `Avatar of ${users.admin.name}` })).toBeVisible();
        await expect(sidebarLink(page, 'Users')).toBeVisible();

        await page.context().close();
    });

    test('the root user can be neither impersonated, edited nor deleted by the other administrators', async ({ adminPage }) => {
        await visit(adminPage, '/app/users');
        await searchTable(adminPage, users.root.email);

        const root = row(adminPage, users.root.email);
        await expect(root).toBeVisible();
        await expect(root.getByRole('button', { name: 'Impersonate' })).toHaveCount(0);
        await expect(root.getByRole('link', { name: 'Edit' })).toHaveCount(0);

        // Another administrator can be impersonated
        await searchTable(adminPage, users.servant.email);
        await expect(row(adminPage, users.servant.email).getByRole('button', { name: 'Impersonate' })).toBeVisible();

        const rootId = php<number>(`return \\App\\Models\\User::where('email', ${JSON.stringify(users.root.email)})->value('id');`);
        await visit(adminPage, `/app/users/${rootId}`);
        await expect(adminPage.getByRole('heading', { name: `View ${users.root.name}` })).toBeVisible();
        const header = adminPage.locator('.fi-header');
        await expect(header.getByRole('button', { name: 'Impersonate' })).toHaveCount(0);
        await expect(header.getByRole('button', { name: 'Delete' })).toHaveCount(0);
        await expect(header.getByRole('button', { name: 'Transfer root' })).toHaveCount(0);
        await expect(header.getByRole('link', { name: 'Edit' })).toHaveCount(0);

        expect((await adminPage.context().request.get(`/app/users/${rootId}/edit`, { maxRedirects: 0 })).status()).toBe(403);
    });
});

test.describe('Deleting users', () => {
    test('a user who has assignments cannot be deleted, and has their access disabled instead', async ({ adminPage }) => {
        const user = createUser();
        php(`
            \\App\\Models\\TerritoryAssignment::factory()->create([
                'territory_id' => \\App\\Models\\Territory::factory()->create([
                    'name' => ${JSON.stringify(uniqueName('Deletion Territory'))},
                    'territory_zone_id' => \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(uniqueName('Deletion Zone'))}])->id,
                ])->id,
                'assignee_id' => ${user.id},
                'started_at' => now()->subMonths(2),
                'ended_at' => now()->subMonth(),
            ]);

            return null;
        `);

        await visit(adminPage, `/app/users/${user.id}`);
        await adminPage.locator('.fi-header').getByRole('button', { name: 'Delete' }).click();
        const dialog = modal(adminPage);
        await expect(dialog).toContainText('Territories have been assigned to this user');
        await expect(dialog).toContainText('Disable their access instead');
        await expect(dialog.getByRole('button', { name: 'Delete' })).toHaveCount(0);

        await dialog.getByRole('button', { name: 'Disable access' }).click();
        await expectNotification(adminPage, `Access disabled: ${user.name} is now a virtual user.`);

        expect(userInDatabase(user.id)).toMatchObject({ can_login: false });
    });

    test('a user without assignments is deleted', async ({ adminPage }) => {
        const user = createUser();

        await visit(adminPage, `/app/users/${user.id}`);
        await adminPage.locator('.fi-header').getByRole('button', { name: 'Delete' }).click();
        await confirmModal(adminPage, 'Delete');
        await expectNotification(adminPage, 'Deleted');

        await expect(adminPage).toHaveURL(/\/app\/users$/);
        expect(userInDatabase(user.id)).toBeNull();
    });
});

test.describe('Publication requests of a user', () => {
    test('list the publications requested by the user', async ({ adminPage }) => {
        const user = createUser();
        const publication = uniqueName('Requested Publication');
        php(`
            \\App\\Models\\PublicationRequest::factory()->create([
                'user_id' => ${user.id},
                'publication_id' => \\App\\Models\\Publication::factory()->create(['name' => ${JSON.stringify(publication)}])->id,
                'quantity' => 2,
            ]);

            return null;
        `);

        await visit(adminPage, `/app/users/${user.id}`);
        await adminPage.getByRole('link', { name: /^Publication requests/ }).click();

        await expect(adminPage).toHaveURL(new RegExp(`/app/users/${user.id}/publication-requests$`));
        await expect(adminPage.getByRole('heading', { name: `Publication requests of ${user.name}`, level: 1 })).toBeVisible();
        await expect(row(adminPage, publication)).toContainText('2');
        await expect(row(adminPage, publication)).toContainText('Requested');
        // One request, besides the summary
        await expect(adminPage.getByRole('row').filter({ hasText: 'Requested Publication' })).toHaveCount(1);
        await expect(adminPage.getByRole('row').filter({ has: adminPage.getByRole('rowheader', { name: 'Summary' }) })).toContainText('Total 2');
    });
});
