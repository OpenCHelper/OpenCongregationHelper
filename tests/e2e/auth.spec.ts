import { type Page } from '@playwright/test';
import { test, expect, logIn, uniqueName, useOwnAddress } from './support/fixtures';
import { php } from './support/artisan';
import { expectNotification, field, modal, settle, visit } from './support/filament';
import { linkInLastMailTo } from './support/mail';
import { totp } from './support/totp';

/**
 * A user of its own for a test that changes it (password, two-factor authentication, failed attempts), with the
 * password `password`, English as language and a verified email.
 */
function createThrowawayUser(attributes: Record<string, unknown> = {}): { id: number; email: string; name: string } {
    const name = uniqueName('Auth');
    const email = `${name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}@e2e.test`;
    const id = php<number>(`
        $user = \\App\\Models\\User::factory()->create(array_merge([
            'first_name' => 'Auth',
            'last_name' => ${JSON.stringify(name.slice(5))},
            'email' => ${JSON.stringify(email)},
            'email_verified_at' => now(),
            'locale' => 'en',
        ], json_decode(${JSON.stringify(JSON.stringify(attributes))}, true)));
        $user->assignRole('publisher');

        return $user->id;
    `);

    return { id, email, name };
}

/**
 * Sign out through the user menu.
 */
async function logOut(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'User menu' }).click();
    await page.getByRole('button', { name: 'Sign out' }).or(page.getByRole('menuitem', { name: 'Sign out' })).first().click();
    await expect(page).toHaveURL(/\/app\/login$/);
}

/**
 * Enable the authenticator app on the profile page, returning its secret and recovery codes.
 */
async function enableAuthenticatorApp(page: Page, password: string): Promise<{ secret: string; recoveryCodes: string[] }> {
    await visit(page, '/app/edit-profile');
    await page.getByRole('button', { name: 'Set up' }).click();

    const dialog = modal(page);
    await expect(dialog.getByRole('heading', { name: 'Set up authenticator app' })).toBeVisible();
    const secret = (await dialog.getByRole('button', { name: /^[A-Z2-7]{16,}$/ }).textContent())!.trim();

    // The form may be sent by itself once the 6 digits are typed: the password first, then "Next" unless it was sent
    await dialog.getByRole('textbox', { name: /Current password/ }).fill(password);
    await dialog.getByRole('textbox', { name: /6-digit code/ }).first().click();
    await page.keyboard.type(totp(secret));
    await settle(page);

    const codes = dialog.getByRole('group', { name: 'recovery' }).getByRole('listitem');
    if (!(await codes.first().isVisible())) {
        await dialog.getByRole('button', { name: 'Next' }).click();
    }
    await expect(codes.first()).toBeVisible();
    const recoveryCodes = (await codes.allTextContents()).map((code) => code.trim());

    await dialog.getByRole('button', { name: 'Enable authenticator app' }).click();
    await expect(page.locator('.fi-sc-actions-label-ctn').filter({ hasText: 'Authenticator app' })).toContainText('Enabled');

    return { secret, recoveryCodes };
}

test.describe('Login', () => {
    test('signs in with the right credentials and opens the dashboard @mobile', async ({ page }) => {
        const user = createThrowawayUser();

        await logIn(page, user.email, 'password');

        await expect(page).toHaveURL(/\/app$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    });

    test('refuses a wrong password', async ({ page }) => {
        const user = createThrowawayUser();

        await logIn(page, user.email, 'not-the-password');

        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('refuses users who are not allowed to log in', async ({ page }) => {
        const user = createThrowawayUser({ can_login: false });

        await logIn(page, user.email, 'password');

        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await visit(page, '/app');
        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('signs out from the user menu', async ({ page }) => {
        const user = createThrowawayUser();
        await logIn(page, user.email, 'password');
        await expect(page).toHaveURL(/\/app$/);

        await logOut(page);

        await visit(page, '/app');
        await expect(page).toHaveURL(/\/app\/login$/);
    });

    test('remembers the user with "Remember me"', async ({ page }) => {
        const user = createThrowawayUser();
        await useOwnAddress(page);
        await visit(page, '/app/login');
        await field(page, 'Email address').fill(user.email);
        await field(page, 'Password').fill('password');
        await page.getByRole('checkbox', { name: 'Remember me' }).check();
        await page.getByRole('button', { name: 'Sign in' }).click();
        await expect(page).toHaveURL(/\/app$/);

        const cookies = await page.context().cookies();
        expect(cookies.some((cookie) => cookie.name.startsWith('remember_web_'))).toBe(true);

        // Without the session cookie, the "remember me" cookie alone signs the user in again
        const remembered = cookies.filter((cookie) => !/session/i.test(cookie.name));
        await page.context().clearCookies();
        await page.context().addCookies(remembered);
        await visit(page, '/app');
        await expect(page).toHaveURL(/\/app$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    });

    test('does not remember the user without "Remember me"', async ({ page }) => {
        const user = createThrowawayUser();
        await logIn(page, user.email, 'password');
        await expect(page).toHaveURL(/\/app$/);

        const cookies = await page.context().cookies();
        expect(cookies.some((cookie) => cookie.name.startsWith('remember_web_'))).toBe(false);
    });

    test('blocks an email after too many failed attempts, whatever the address', async ({ page }) => {
        const user = createThrowawayUser();
        await visit(page, '/app/login');
        await field(page, 'Email address').fill(user.email);

        // Every attempt from a new address, so that only the throttling by email can stop them (10 per 15 minutes)
        for (let attempt = 1; attempt <= 10; attempt++) {
            await useOwnAddress(page);
            await field(page, 'Password').fill(`wrong-password-${attempt}`);
            await page.getByRole('button', { name: 'Sign in' }).click();
            await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
            await settle(page);
        }

        await useOwnAddress(page);
        await field(page, 'Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expectNotification(page, 'Too many login attempts');
        await expect(page).toHaveURL(/\/app\/login$/);
    });
});

test.describe('Password reset', () => {
    test('resets the password from the link sent by email', async ({ page }) => {
        const user = createThrowawayUser();
        const newPassword = 'E2e-Reset-Passw0rd!';
        await useOwnAddress(page);

        await visit(page, '/app/login');
        await page.getByRole('link', { name: 'Forgot password?' }).click();
        await expect(page).toHaveURL(/\/app\/password-reset\/request$/);

        await field(page, 'Email address').fill(user.email);
        await page.getByRole('button', { name: 'Send email' }).click();
        await expectNotification(page, /sent|email/i);

        await expect.poll(() => {
            try {
                return linkInLastMailTo(user.email, /password-reset\/reset/);
            } catch {
                return null;
            }
        }).not.toBeNull();
        await visit(page, linkInLastMailTo(user.email, /password-reset\/reset/));

        await expect(field(page, 'Email address')).toHaveValue(user.email);
        await field(page, 'Password').fill(newPassword);
        await field(page, 'Confirm password').fill(newPassword);
        await page.getByRole('button', { name: 'Reset password' }).click();
        await expect(page).toHaveURL(/\/app\/login$/);

        await logIn(page, user.email, 'password');
        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();

        await logIn(page, user.email, newPassword);
        await expect(page).toHaveURL(/\/app$/);
    });
});

test.describe('Two-factor authentication', () => {
    test('enables the authenticator app, then asks for its code when signing in', async ({ page }) => {
        const user = createThrowawayUser();
        await logIn(page, user.email, 'password');
        await expect(page).toHaveURL(/\/app$/);

        const { secret } = await enableAuthenticatorApp(page, 'password');
        expect(php<boolean>(`return filled(\\App\\Models\\User::find(${user.id})->getAppAuthenticationSecret());`)).toBe(true);

        await logOut(page);
        await logIn(page, user.email, 'password');

        // The form is sent as soon as the 6 digits are typed
        const code = page.getByRole('textbox', { name: /6-digit code/ }).first();
        await code.click();
        await page.keyboard.type('000000');
        await expect(page.getByText('The code you entered is invalid.')).toBeVisible();
        await expect(page).toHaveURL(/\/app\/login$/);

        await logIn(page, user.email, 'password');
        // A code of a later period: the one used to enable the app cannot be used again
        await page.getByRole('textbox', { name: /6-digit code/ }).first().click();
        await page.keyboard.type(totp(secret, Date.now() + 60_000));

        await expect(page).toHaveURL(/\/app$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    });

    test('signs in with a recovery code instead of the code of the app', async ({ page }) => {
        const user = createThrowawayUser();
        await logIn(page, user.email, 'password');
        await expect(page).toHaveURL(/\/app$/);

        const { recoveryCodes } = await enableAuthenticatorApp(page, 'password');
        expect(recoveryCodes).toHaveLength(8);

        await logOut(page);
        await logIn(page, user.email, 'password');

        await page.getByRole('button', { name: 'Use a recovery code instead' }).click();
        await settle(page);
        await page.getByRole('textbox', { name: 'Or, enter a recovery code' }).fill(recoveryCodes[0]);
        // The field is sent to the server when it loses the focus: a click on the button meanwhile can get lost
        const committed = page.waitForResponse((response) => response.url().includes('/update'));
        await page.getByRole('textbox', { name: 'Or, enter a recovery code' }).press('Tab');
        await committed;
        await page.getByRole('button', { name: 'Confirm sign in' }).click();

        await expect(page).toHaveURL(/\/app$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    });
});
