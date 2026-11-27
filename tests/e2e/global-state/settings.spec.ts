import { type Browser, type Page } from '@playwright/test';
import { php } from '../support/artisan';
import { expect, pageAs, test, uniqueName } from '../support/fixtures';
import { confirmModal, field, modal, settle, visit } from '../support/filament';

/**
 * The settings of the application (Administration > Settings), which change what every user sees: these tests run
 * alone (see the `global-state` project), and put the defaults back after each test.
 */
test.afterEach(() => {
    php(`
        $general = app(\\App\\Settings\\GeneralSettings::class);
        $general->app_name = null;
        $general->kingdom_hall_address = null;
        $general->save();

        $territories = app(\\App\\Settings\\TerritorySettings::class);
        $territories->map_default_center = null;
        $territories->sharing_enabled = true;
        $territories->shared_pii = null;
        $territories->guest_returns = null;
        $territories->stale_after_days = 60;
        $territories->dead_after_days = 150;
        $territories->save();

        $campaigns = app(\\App\\Settings\\CampaignSettings::class);
        $campaigns->expiring_days = 7;
        $campaigns->almost_expired_days = 3;
        $campaigns->save();

        return true;
    `);
});

async function save(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Save changes' }).click();
    await settle(page);
}

/**
 * A territory assigned to the publisher, shared through a public link.
 */
function sharedAssignment(): { id: number; url: string } {
    return php(`
        $territory = \\App\\Models\\Territory::factory()->withMap()->create([
            'name' => ${JSON.stringify(uniqueName('Shared'))},
            'notes' => 'Ring twice',
            'territory_zone_id' => \\App\\Models\\TerritoryZone::where('name', 'E2E Zone')->value('id'),
        ]);
        $assignment = \\App\\Models\\TerritoryAssignment::factory()->create([
            'territory_id' => $territory->id,
            'assignee_id' => \\App\\Models\\User::where('email', 'publisher@e2e.test')->value('id'),
            'campaign_id' => null,
            'started_at' => now()->subDay(),
            'ended_at' => null,
        ]);

        return ['id' => $assignment->id, 'url' => $assignment->share()->getShareableUrl()];
    `);
}

async function openAsGuest(browser: Browser, url: string): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await page.goto(url);
    await settle(page);

    return page;
}

test.describe('general settings', () => {
    test('the name of the congregation is shown in the whole app', async ({ adminPage, browser }) => {
        const name = uniqueName('Congregation');

        await visit(adminPage, '/app/settings/general');
        await field(adminPage, 'Name of the app').fill(name);
        await field(adminPage, 'Kingdom Hall address').fill('1 Main Street, Springfield');
        await save(adminPage);

        await expect(adminPage.locator('.fi-no-notification').filter({ hasText: 'Saved' })).toBeVisible();

        // Another user sees it in the top bar and the title of the pages
        const publisher = await pageAs(browser, 'publisher');
        await visit(publisher, '/app');
        await expect(publisher.getByRole('link', { name }).first()).toBeVisible();
        await expect(publisher).toHaveTitle(new RegExp(name));
        await publisher.context().close();

        await visit(adminPage, '/app/settings/general');
        await expect(field(adminPage, 'Kingdom Hall address')).toHaveValue('1 Main Street, Springfield');
    });

    test('the name of the congregation is required', async ({ adminPage }) => {
        await visit(adminPage, '/app/settings/general');
        const name = field(adminPage, 'Name of the app');
        await name.fill('');
        await save(adminPage);

        // The browser refuses to submit the form, and the name is kept
        expect(await name.evaluate((input: HTMLInputElement) => input.validity.valueMissing)).toBe(true);
        expect(php<string | null>('return app(\\App\\Settings\\GeneralSettings::class)->app_name;')).toBeNull();
    });
});

test.describe('territory settings', () => {
    test('the default map centre is saved and validated', async ({ adminPage }) => {
        await visit(adminPage, '/app/settings/territories');
        const centre = field(adminPage, 'Default map centre');

        await centre.fill('not a position');
        await save(adminPage);
        await expect(adminPage.locator('.fi-fo-field-wrp-error-message, [data-validation-error]').first()).toBeVisible();

        await centre.fill('45.464211, 9.191383');
        await save(adminPage);
        await adminPage.reload();
        await settle(adminPage);
        await expect(field(adminPage, 'Default map centre')).toHaveValue('45.464211, 9.191383');
    });

    test('disabling sharing deletes the shared links and hides the share action', async ({ adminPage, browser }) => {
        const assignment = sharedAssignment();

        await visit(adminPage, '/app/settings/territories');
        await adminPage.getByRole('switch', { name: 'Share assignments' }).click();

        // Turning it off asks first, since saving deletes the links
        await expect(modal(adminPage)).toContainText('Disable sharing?');
        await expect(modal(adminPage)).toContainText(/existing shared link/);
        await confirmModal(adminPage, 'Disable sharing');
        await settle(adminPage);
        await expect(adminPage.getByRole('switch', { name: 'Share assignments' })).not.toBeChecked();
        await save(adminPage);

        expect(php<number>('return \\App\\Models\\SharedUrl::count();')).toBe(0);

        const guest = await openAsGuest(browser, assignment.url);
        await expect(guest.getByText(/404|not found/i).first()).toBeVisible();
        await guest.context().close();

        await visit(adminPage, `/app/territory-assignments/${assignment.id}`);
        await expect(adminPage.getByRole('button', { name: 'Share' })).toHaveCount(0);
    });

    test('hiding the personal data removes the assignee from the shared pages', async ({ browser, adminPage }) => {
        const assignment = sharedAssignment();

        const before = await openAsGuest(browser, assignment.url);
        await expect(before.getByText('Paul Publisher')).toBeVisible();
        await before.context().close();

        await visit(adminPage, '/app/settings/territories');
        await adminPage.getByRole('radio', { name: /Hide personal data/ }).check();
        await save(adminPage);

        const after = await openAsGuest(browser, assignment.url);
        await expect(after.getByText('Paul Publisher')).toHaveCount(0);
        await expect(after.getByText('Ring twice')).toHaveCount(0);
        await after.context().close();
    });

    test('showing the personal data asks for a confirmation', async ({ adminPage }) => {
        php(`
            $settings = app(\\App\\Settings\\TerritorySettings::class);
            $settings->shared_pii = false;
            $settings->save();

            return true;
        `);

        await visit(adminPage, '/app/settings/territories');
        await adminPage.getByRole('radio', { name: /Show personal data/ }).check();

        const confirmation = modal(adminPage);
        await expect(confirmation).toContainText('Show personal data on shared pages?');

        // The confirmation needs the box ticked
        await confirmModal(adminPage, 'Show personal data');
        await expect(confirmation.getByText('Confirm that you understand what showing the personal data means.')).toBeVisible();

        await confirmation.getByRole('checkbox').check();
        await confirmModal(adminPage, 'Show personal data');
        await settle(adminPage);
        await expect(adminPage.getByRole('radio', { name: /Show personal data/ })).toBeChecked();
        await save(adminPage);

        expect(php<boolean>('return app(\\App\\Settings\\TerritorySettings::class)->shared_pii;')).toBe(true);
    });

    test('the shared pages can stop offering to return the territory', async ({ browser, adminPage }) => {
        const assignment = sharedAssignment();

        const before = await openAsGuest(browser, assignment.url);
        await expect(before.getByRole('button', { name: 'Ask to return' })).toBeVisible();
        await before.context().close();

        await visit(adminPage, '/app/settings/territories');
        await adminPage.getByRole('switch', { name: 'Ask to return from shared pages' }).click();
        await save(adminPage);

        const after = await openAsGuest(browser, assignment.url);
        await expect(after.getByRole('heading').first()).toBeVisible();
        await expect(after.getByRole('button', { name: 'Ask to return' })).toHaveCount(0);
        await after.context().close();
    });

    test('the days of the assignments out for too long are saved', async ({ adminPage }) => {
        await visit(adminPage, '/app/settings/territories');
        await field(adminPage, 'Out for too long').fill('45');
        await field(adminPage, 'Lost').fill('120');
        await save(adminPage);

        expect(php<number[]>(`
            $settings = app(\\App\\Settings\\TerritorySettings::class);

            return [$settings->stale_after_days, $settings->dead_after_days];
        `)).toEqual([45, 120]);
    });
});

test.describe('campaign settings', () => {
    test('the expiry days change the status of the campaigns', async ({ adminPage }) => {
        const name = uniqueName('Expiring campaign');
        php(`
            \\App\\Models\\Campaign::factory()->create([
                'name' => ${JSON.stringify(name)},
                'valid_from' => now()->subWeek(),
                'valid_until' => now()->addDays(5)->endOfDay(),
            ]);

            return true;
        `);

        await visit(adminPage, '/app/campaigns');
        const row = adminPage.locator('.fi-ta-row').filter({ hasText: name });
        await expect(row).toContainText('Expiring');

        await visit(adminPage, '/app/settings/campaigns');
        await field(adminPage, 'Expiring').fill('4');
        await field(adminPage, 'Almost Expired').fill('2');
        await save(adminPage);

        await visit(adminPage, '/app/campaigns');
        await expect(adminPage.locator('.fi-ta-row').filter({ hasText: name })).toContainText('Active');
    });
});
