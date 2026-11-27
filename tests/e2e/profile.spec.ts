import { type Browser, type Page } from '@playwright/test';
import { test, expect, logIn, uniqueName } from './support/fixtures';
import { php } from './support/artisan';
import { choose, expectNotification, field, modal, settle, visit } from './support/filament';

type ThrowawayUser = { id: number; email: string; firstName: string; lastName: string };

/**
 * A user of its own for a test that changes it, with the password `password`, English as language and a verified email.
 */
function createThrowawayUser(): ThrowawayUser {
    const lastName = uniqueName('Profile').slice('Profile '.length);
    const email = `profile-${lastName}@e2e.test`;
    const id = php<number>(`
        $user = \\App\\Models\\User::factory()->create([
            'first_name' => 'Pat',
            'last_name' => ${JSON.stringify(lastName)},
            'email' => ${JSON.stringify(email)},
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);
        $user->assignRole('publisher');

        return $user->id;
    `);

    return { id, email, firstName: 'Pat', lastName };
}

/**
 * Sign in with a new throwaway user in a browser context of its own, on the profile page.
 */
async function signInAsThrowawayUser(browser: Browser, user: ThrowawayUser = createThrowawayUser()): Promise<{ page: Page; user: ThrowawayUser }> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await logIn(page, user.email, 'password');
    await expect(page).toHaveURL(/\/app$/);
    await visit(page, '/app/edit-profile');

    return { page, user };
}

/**
 * A section of the profile page, by its heading.
 */
const section = (page: Page, heading: string) =>
    page.locator('.fi-section, [class*="grid"]').filter({ has: page.getByRole('heading', { level: 2, name: heading, exact: true }) }).last();

/**
 * Open an item of the user menu.
 */
async function openUserMenuItem(page: Page, item: string): Promise<void> {
    await page.getByRole('button', { name: 'User menu' }).click();
    await page.getByRole('menuitem', { name: item }).click();
}

test.describe('Profile information', () => {
    test('updates the name and the email', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);
        const email = `changed-${user.email}`;
        const information = section(page, 'Profile information');

        await field(information, 'First name').fill('Renamed');
        await field(information, 'Email').fill(email);
        await information.getByRole('button', { name: 'Save' }).click();

        await expectNotification(page, 'Saved.');
        expect(php(`$user = \\App\\Models\\User::find(${user.id}); return [$user->first_name, $user->email];`)).toEqual(['Renamed', email]);

        await visit(page, '/app/edit-profile');
        await expect(page.getByRole('button', { name: 'User menu' }).getByRole('img')).toHaveAccessibleName(`Avatar of Renamed ${user.lastName}`);
        await page.context().close();
    });

    test('refuses an email already used by someone else', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);
        const information = section(page, 'Profile information');

        await field(information, 'Email').fill('admin@e2e.test');
        await information.getByRole('button', { name: 'Save' }).click();

        await expect(information.getByText(/already been taken/)).toBeVisible();
        expect(php(`return \\App\\Models\\User::find(${user.id})->email;`)).toBe(user.email);
        await page.context().close();
    });
});

test.describe('Contacts', () => {
    test('adds, edits and deletes a contact', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);
        const contacts = section(page, 'Contact information');
        await expect(contacts.getByText('No contacts added.')).toBeVisible();

        await contacts.getByRole('button', { name: 'Add contact' }).first().click();
        let dialog = modal(page);
        await choose(dialog, 'Type', 'Emergency');
        await field(dialog, 'Name').fill('Mario Rossi');
        await field(dialog, 'Phone number').fill('+39 333 1234567');
        await dialog.getByRole('button', { name: 'Submit' }).click();

        await expectNotification(page, 'Contact saved.');
        await expect(contacts.getByRole('row', { name: /Mario Rossi/ })).toBeVisible();
        expect(php(`return \\App\\Models\\User::find(${user.id})->contacts()->pluck('phone_number', 'name');`)).toEqual({ 'Mario Rossi': '+39 333 1234567' });

        await contacts.getByRole('row', { name: /Mario Rossi/ }).getByRole('button', { name: 'Edit' }).click();
        dialog = modal(page);
        await field(dialog, 'Name').fill('Maria Rossi');
        await dialog.getByRole('button', { name: /^(Submit|Save|Save changes|Update)$/ }).first().click();
        await expect(contacts.getByRole('row', { name: /Maria Rossi/ })).toBeVisible();

        await contacts.getByRole('row', { name: /Maria Rossi/ }).getByRole('button', { name: 'Delete' }).click();
        await modal(page).getByRole('button', { name: /^(Delete|Confirm)$/ }).first().click();

        await expectNotification(page, 'Contact deleted.');
        await expect(contacts.getByText('No contacts added.')).toBeVisible();
        expect(php(`return \\App\\Models\\User::find(${user.id})->contacts()->count();`)).toBe(0);
        await page.context().close();
    });

    test('needs a phone number or an address', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);
        const contacts = section(page, 'Contact information');

        await contacts.getByRole('button', { name: 'Add contact' }).first().click();
        const dialog = modal(page);
        await choose(dialog, 'Type', 'Personal');
        await field(dialog, 'Name').fill('Nobody');
        await dialog.getByRole('button', { name: 'Submit' }).click();

        await expect(dialog.getByText(/phone number field is required/i)).toBeVisible();
        expect(php(`return \\App\\Models\\User::find(${user.id})->contacts()->count();`)).toBe(0);
        await page.context().close();
    });
});

test.describe('Password and sessions', () => {
    test('changes the password and signs in with the new one', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);
        const newPassword = 'E2e-Profile-Passw0rd!';
        const password = section(page, 'Update password');

        await field(password, 'Current password').fill('password');
        await field(password, 'New password').fill(newPassword);
        await field(password, 'Confirm password').fill(newPassword);
        await password.getByRole('button', { name: 'Save' }).click();
        await expectNotification(page, 'Saved.');

        await openUserMenuItem(page, 'Sign out');
        await expect(page).toHaveURL(/\/app\/login$/);

        await logIn(page, user.email, 'password');
        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();

        await logIn(page, user.email, newPassword);
        await expect(page).toHaveURL(/\/app$/);
        await page.context().close();
    });

    test('refuses a wrong current password', async ({ browser }) => {
        const { page } = await signInAsThrowawayUser(browser);
        const password = section(page, 'Update password');

        await field(password, 'Current password').fill('not-the-password');
        await field(password, 'New password').fill('E2e-Profile-Passw0rd!');
        await field(password, 'Confirm password').fill('E2e-Profile-Passw0rd!');
        await password.getByRole('button', { name: 'Save' }).click();

        await expect(password.getByText(/does not match your current password/i)).toBeVisible();
        await page.context().close();
    });

    test('logs out the other browser sessions', async ({ browser }) => {
        // Jetstream lists and logs out the other sessions only with the `database` session driver (the default one),
        // while `serve.sh` serves the end-to-end tests with the `file` driver: the button then does nothing
        test.skip(php<string>(`return config('session.driver');`) !== 'database', 'Needs the database session driver');

        const { page, user } = await signInAsThrowawayUser(browser);
        const { page: otherPage } = await signInAsThrowawayUser(browser, user);

        await section(page, 'Browser sessions').getByRole('button', { name: 'Log out other browser sessions' }).click();
        const dialog = modal(page);
        await dialog.getByRole('textbox', { name: /password/i }).fill('password');
        await dialog.getByRole('button', { name: 'Log out other browser sessions' }).click();
        await expectNotification(page, 'Logged out of your other browser sessions.');

        // This session goes on, the other one has been signed out
        await visit(page, '/app/edit-profile');
        await expect(page).toHaveURL(/\/app\/edit-profile$/);
        await visit(otherPage, '/app/edit-profile');
        await expect(otherPage).toHaveURL(/\/app\/login$/);

        await otherPage.context().close();
        await page.context().close();
    });

    test('deletes the own account', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);

        await section(page, 'Delete account').getByRole('button', { name: 'Delete account' }).click();
        const dialog = modal(page);
        await dialog.getByRole('textbox', { name: /password/i }).fill('password');
        await dialog.getByRole('button', { name: 'Delete account' }).click();

        await expect(page).not.toHaveURL(/edit-profile/);
        await expect.poll(() => php(`return \\App\\Models\\User::find(${user.id}) === null;`)).toBe(true);

        await logIn(page, user.email, 'password');
        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await page.context().close();
    });
});

test.describe('User menu', () => {
    test('switches the theme from the UI preferences', async ({ browser }) => {
        const { page } = await signInAsThrowawayUser(browser);
        const html = page.locator('html');
        await expect(html).not.toHaveClass(/\bdark\b/);

        await openUserMenuItem(page, 'Settings');
        await page.getByRole('menuitemradio', { name: 'Enable dark theme' }).click();
        await expect(html).toHaveClass(/\bdark\b/);

        // Kept by the browser on the next pages
        await visit(page, '/app');
        await expect(html).toHaveClass(/\bdark\b/);

        await openUserMenuItem(page, 'Settings');
        await page.getByRole('menuitemradio', { name: 'Enable light theme' }).click();
        await expect(html).not.toHaveClass(/\bdark\b/);
        await page.context().close();
    });

    test('changes the layout and the primary color from the UI preferences', async ({ browser }) => {
        const { page } = await signInAsThrowawayUser(browser);
        const body = page.locator('body');
        // The page reloads after each choice: the color is read once it has loaded again
        const primaryColor = () => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--primary-500').trim()).catch(() => '');
        const initialColor = await primaryColor();
        await expect(body).not.toHaveClass(/fi-body-has-top-navigation/);

        await openUserMenuItem(page, 'Settings');
        await Promise.all([
            page.waitForEvent('load'),
            page.getByRole('button', { name: 'Topbar', exact: true }).click(),
        ]);
        await settle(page);
        await expect(body).toHaveClass(/fi-body-has-top-navigation/);

        await openUserMenuItem(page, 'Settings');
        // The colors have no name: the green one
        await Promise.all([
            page.waitForEvent('load'),
            page.locator('[wire\\:click="setColor(\'#10b981\')"]').filter({ visible: true }).click(),
        ]);
        await settle(page);
        await expect.poll(primaryColor).not.toBe(initialColor);

        // Kept on the next pages
        await visit(page, '/app');
        await expect(body).toHaveClass(/fi-body-has-top-navigation/);
        expect(await primaryColor()).not.toBe(initialColor);
        await page.context().close();
    });

    test('shows the versions, with a link to report a bug', async ({ adminPage }) => {
        await visit(adminPage, '/app');

        await openUserMenuItem(adminPage, 'About');

        const dialog = modal(adminPage);
        await expect(dialog.getByRole('heading', { name: 'Version information' })).toBeVisible();
        for (const term of ['Laravel', 'Filament', 'PHP', 'App']) {
            await expect(dialog.getByRole('term').filter({ hasText: new RegExp(`^\\s*${term}\\s*$`) })).toBeVisible();
        }

        const report = dialog.getByRole('link', { name: 'Report a bug' });
        await expect(report).toHaveAttribute('target', '_blank');
        const url = new URL((await report.getAttribute('href'))!);
        expect(url.pathname).toMatch(/\/issues\/new$/);
        expect(url.searchParams.get('template')).toBeTruthy();
        expect(url.searchParams.get('instance')).toBe(new URL(adminPage.url()).origin);
        expect(url.searchParams.get('version')).toBeTruthy();
    });
});

test.describe('Installing the app', () => {
    test('an iPhone shows the steps to add the app to the home screen, from the profile and the user menu @mobile', async ({ adminPage, browserName, isMobile }) => {
        test.skip(browserName !== 'webkit' || !isMobile, 'Elsewhere the browser offers to install the app itself, when it decides to (Chromium does not here).');

        await visit(adminPage, '/app/edit-profile');
        const installOffer = adminPage.getByRole('dialog', { name: /^Install / });

        const installSection = section(adminPage, 'Install the app');
        await expect(installSection).toContainText('Open it from your home screen or desktop');
        await installSection.getByRole('button', { name: 'Install' }).click();

        // iOS has no install prompt: the steps are shown instead, with the notifications that need the app installed
        await expect(installOffer).toContainText('"Add to Home Screen"');
        await expect(installOffer).toContainText('Notifications on iPhone and iPad');
        await expect(installOffer.getByRole('button', { name: 'Install', exact: true })).toBeHidden();
        await installOffer.getByRole('button', { name: 'Not now' }).click();
        await expect(installOffer).toBeHidden();

        await openUserMenuItem(adminPage, 'Install app');
        await expect(installOffer).toContainText('"Add to Home Screen"');
        await adminPage.keyboard.press('Escape');
        await expect(installOffer).toBeHidden();
    });
});

test.describe('Language', () => {
    test('switches the interface to Italian from the top bar', async ({ browser }) => {
        const { page } = await signInAsThrowawayUser(browser);
        await expect(page.getByRole('heading', { level: 1, name: 'Profile' })).toBeVisible();

        await page.getByRole('navigation', { name: 'Topbar' }).getByRole('button', { name: 'English' }).click();
        await page.getByRole('menuitem', { name: /Italian/ }).click();
        await settle(page);

        await expect(page.getByRole('heading', { level: 1, name: 'Profilo' })).toBeVisible();
        await visit(page, '/app/edit-profile');
        await expect(page.getByRole('heading', { level: 1, name: 'Profilo' })).toBeVisible();

        await page.getByRole('navigation', { name: 'Topbar' }).getByRole('button', { name: /Italian|Italiano/ }).click();
        await page.getByRole('menuitem', { name: /English|Inglese/ }).click();
        await settle(page);
        await expect(page.getByRole('heading', { level: 1, name: 'Profile' })).toBeVisible();
        await page.context().close();
    });

    test('saves the preferred language of the user', async ({ browser }) => {
        const { page, user } = await signInAsThrowawayUser(browser);
        const information = section(page, 'Profile information');

        await field(information, 'Preferred language').selectOption({ label: 'Italiano (it)' });
        await information.getByRole('button', { name: 'Save' }).click();
        await expect.poll(() => php(`return \\App\\Models\\User::find(${user.id})->locale;`)).toBe('it');
        await page.context().close();
    });
});
