import { type Page } from '@playwright/test';
import { php } from '../support/artisan';
import { expect, logIn, test, uniqueName } from '../support/fixtures';
import { field, settle, visit } from '../support/filament';
import { linkInLastMailTo } from '../support/mail';
import { totp } from '../support/totp';

/**
 * The account requirements and the AI assistants of the Security settings, which apply to every user: these tests
 * run alone (see the `global-state` project), and turn them off again after each test.
 */
test.afterEach(() => {
    php(`
        $settings = app(\\App\\Settings\\SecuritySettings::class);
        $settings->require_two_factor = false;
        $settings->require_email_verification = false;
        $settings->ai_assistants_enabled = false;
        $settings->save();

        // The root user as \`och:install\` creates it (saved quietly: its account is protected outside production)
        \\App\\Models\\User::rootUser()->forceFill([
            'email_verified_at' => null,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->saveQuietly();

        return true;
    `);
});

/**
 * Make the root user meet a requirement, which it must before the requirement can be turned on.
 */
function rootUserMeets(requirement: 'two-factor' | 'email'): void {
    php(`
        \\App\\Models\\User::rootUser()->forceFill(${requirement === 'email'
            ? "['email_verified_at' => now()]"
            : "['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']"})->saveQuietly();

        return true;
    `);
}

/**
 * A publisher who can log in, with the password `password`.
 */
function createUser(attributes: { verified?: boolean } = {}): string {
    const email = `${uniqueName('security').replaceAll(' ', '-').toLowerCase()}@e2e.test`;

    php(`
        \\App\\Models\\User::factory()->create([
            'email' => ${JSON.stringify(email)},
            'password' => 'password',
            'can_login' => true,
            'locale' => 'en',
            'email_verified_at' => ${attributes.verified === false ? 'null' : 'now()'},
        ])->assignRole('publisher');

        return true;
    `);

    return email;
}

async function turnOn(page: Page, requirement: string): Promise<void> {
    await visit(page, '/app/settings/security');
    const toggle = page.getByRole('switch', { name: requirement });
    await toggle.click();
    await expect(toggle).toBeChecked();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await settle(page);
}

async function typeCode(page: Page, code: string): Promise<void> {
    await page.getByRole('textbox', { name: /Enter the 6-digit code/ }).first().click();
    await page.keyboard.type(code);
}

test('two-factor authentication can be required only once the root user has it', async ({ adminPage }) => {
    await turnOn(adminPage, 'Require two-factor authentication');

    await expect(adminPage.getByText('Set up two-factor authentication for the root user first, so that it cannot be locked out.')).toBeVisible();
    expect(php<boolean>('return app(\\App\\Settings\\SecuritySettings::class)->require_two_factor;')).toBe(false);
});

test('the users must set up two-factor authentication when it is required', async ({ adminPage, page }) => {
    rootUserMeets('two-factor');
    await turnOn(adminPage, 'Require two-factor authentication');
    expect(php<boolean>('return app(\\App\\Settings\\SecuritySettings::class)->require_two_factor;')).toBe(true);

    const email = createUser();
    await logIn(page, email, 'password');

    // Every page leads to the set-up until it is done
    await expect(page).toHaveURL(/\/multi-factor-authentication\/set-up/);
    await page.goto('/app/territories');
    await expect(page).toHaveURL(/\/multi-factor-authentication\/set-up/);
    await expect(page.getByRole('heading', { name: 'Set up two-factor authentication' })).toBeVisible();

    await page.getByRole('button', { name: 'Set up' }).click();
    const dialog = page.getByRole('dialog', { name: 'Set up authenticator app' });
    const secret = (await dialog.getByRole('button', { name: /^[A-Z2-7]{16,}$/ }).innerText()).trim();

    await typeCode(page, totp(secret));
    await field(dialog, 'Current password').fill('password');
    await dialog.getByRole('button', { name: 'Next' }).click();
    await settle(page);

    // The recovery codes are shown once, before enabling it
    await expect(dialog.getByText(/save the following recovery codes/)).toBeVisible();
    await expect(dialog.getByRole('listitem')).toHaveCount(8);
    await dialog.getByRole('button', { name: 'Enable authenticator app' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('Authenticator app Enabled')).toBeVisible();

    // Then the page asked for at first opens
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/\/app\/territories$/);
    await expect(page.getByRole('heading', { name: 'Territories', level: 1 })).toBeVisible();
    expect(php<boolean>(`return \\App\\Models\\User::where('email', ${JSON.stringify(email)})->sole()->hasTwoFactorAuthentication();`)).toBe(true);
});

test('the email confirmation can be required only once the root user has confirmed its own', async ({ adminPage }) => {
    await turnOn(adminPage, 'Require email confirmation');

    await expect(adminPage.getByText('Confirm the email address of the root user first, so that it cannot be locked out.')).toBeVisible();
    expect(php<boolean>('return app(\\App\\Settings\\SecuritySettings::class)->require_email_verification;')).toBe(false);
});

test('the users must confirm their email address when it is required', async ({ adminPage, page }) => {
    rootUserMeets('email');
    await turnOn(adminPage, 'Require email confirmation');
    expect(php<boolean>('return app(\\App\\Settings\\SecuritySettings::class)->require_email_verification;')).toBe(true);

    const email = createUser({ verified: false });
    await logIn(page, email, 'password');

    await expect(page).toHaveURL(/\/email-verification\/prompt/);
    await expect(page.getByRole('heading', { name: 'Verify your email address' })).toBeVisible();

    await page.getByRole('button', { name: 'Resend it.' }).click();
    await settle(page);

    await page.goto(linkInLastMailTo(email, /verify/));
    await expect(page).toHaveURL(/\/app\/?$/);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    expect(php<boolean>(`return \\App\\Models\\User::where('email', ${JSON.stringify(email)})->sole()->hasVerifiedEmail();`)).toBe(true);
});

test('the unconfirmed users can use the app while the confirmation is not required', async ({ page }) => {
    const email = createUser({ verified: false });
    await logIn(page, email, 'password');

    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
});

test('allowing the AI assistants opens the MCP server and the profile section', async ({ adminPage, request }) => {
    const callServer = async (): Promise<number> =>
        (await request.post('/mcp', { headers: { Accept: 'application/json' }, maxRedirects: 0 })).status();

    expect(await callServer()).toBe(404);

    await visit(adminPage, '/app/edit-profile');
    await expect(adminPage.getByRole('heading', { name: 'AI assistants' })).toHaveCount(0);

    await turnOn(adminPage, 'Allow AI assistants');
    expect(php<boolean>('return app(\\App\\Settings\\SecuritySettings::class)->ai_assistants_enabled;')).toBe(true);

    // The MCP server now asks the assistants to authenticate
    expect(await callServer()).toBe(401);

    await visit(adminPage, '/app/edit-profile');
    await expect(adminPage.getByRole('heading', { name: 'AI assistants' })).toBeVisible();
});
