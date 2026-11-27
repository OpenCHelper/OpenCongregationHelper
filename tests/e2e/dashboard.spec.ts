import { type Browser, type Page } from '@playwright/test';
import { test, expect, logIn, pageAs, uniqueName } from './support/fixtures';
import { php } from './support/artisan';
import { settle, visit } from './support/filament';

/**
 * A publisher of its own (the dashboard remembers some choices in the session, e.g. the coverage period), signed in
 * in a browser context of its own on the dashboard.
 */
async function signInAsThrowawayPublisher(browser: Browser): Promise<{ page: Page; id: number }> {
    const lastName = uniqueName('Dashboard').slice('Dashboard '.length);
    const email = `dashboard-${lastName}@e2e.test`;
    const id = php<number>(`
        $user = \\App\\Models\\User::factory()->create([
            'first_name' => 'Dana',
            'last_name' => ${JSON.stringify(lastName)},
            'email' => ${JSON.stringify(email)},
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);
        $user->assignRole('publisher');

        return $user->id;
    `);

    const page = await (await browser.newContext()).newPage();
    await logIn(page, email, 'password');
    await expect(page).toHaveURL(/\/app$/);
    await settle(page);

    return { page, id };
}

/**
 * The number of territories worked at least once in the period chosen in the coverage widget.
 */
async function workedTerritories(page: Page): Promise<number> {
    const text = await page.locator('main').innerText();
    const match = text.match(/worked at least once\s*[\d.,]+%\s*\((\d+) of \d+\)/);
    expect(match, 'the coverage widget shows the territories worked').not.toBeNull();

    return Number(match![1]);
}

test.describe('Dashboard widgets', () => {
    test('shows the statistics, the coverage and the map to the administrators', async ({ adminPage }) => {
        await visit(adminPage, '/app');
        const main = adminPage.getByRole('main');

        await expect(main.getByText('Total number of territories')).toBeVisible();
        await expect(main.getByText('Average monthly assignment duration')).toBeVisible();
        await expect(main.getByRole('heading', { name: 'Territory coverage' })).toBeVisible();
        await expect(main.getByRole('heading', { name: 'Territory map' })).toBeVisible();
        await expect(main.getByText('Your account cannot see the territories yet')).toHaveCount(0);
    });

    test('shows the territory widgets to the publishers, whose role lets them see the territories @mobile', async ({ publisherPage }) => {
        await visit(publisherPage, '/app');
        const main = publisherPage.getByRole('main');

        await expect(main.getByText('Total number of territories')).toBeVisible();
        await expect(main.getByRole('heading', { name: 'Territory coverage' })).toBeVisible();
        await expect(main.getByRole('heading', { name: 'Territory map' })).toBeVisible();
    });

    test('tells users without a role that there is nothing to see', async ({ browser }) => {
        const page = await pageAs(browser, 'norole');
        await visit(page, '/app');
        const main = page.getByRole('main');

        await expect(main.getByRole('heading', { name: 'Welcome, Nora Norole' })).toBeVisible();
        await expect(main.getByText('Your account cannot see the territories yet')).toBeVisible();
        await expect(main.getByText('Total number of territories')).toHaveCount(0);
        await expect(main.getByRole('heading', { name: 'Territory map' })).toHaveCount(0);
        await expect(main.getByText('E2E Zone')).toHaveCount(0);
        await page.context().close();
    });
});

test.describe('Territory coverage', () => {
    test('changes the figures with the period', async ({ browser }) => {
        // A territory worked only 18 months ago: covered in the last 24 months, not in the last 6
        php(`
            $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(uniqueName('Coverage Zone'))}]);
            $territory = \\App\\Models\\Territory::factory()->for($zone)->create(['name' => ${JSON.stringify(uniqueName('Coverage'))}]);
            \\App\\Models\\TerritoryAssignment::factory()->create([
                'territory_id' => $territory->id,
                'assignee_id' => \\App\\Models\\User::where('email', 'publisher@e2e.test')->value('id'),
                'campaign_id' => null,
                'started_at' => now()->subMonths(19),
                'ended_at' => now()->subMonths(18),
            ]);

            return $territory->id;
        `);
        const { page } = await signInAsThrowawayPublisher(browser);
        const period = page.getByRole('combobox', { name: 'Period' });

        await period.selectOption({ label: 'Last 6 months' });
        await settle(page);
        await expect(period).toHaveValue('6');
        const inSixMonths = await workedTerritories(page);

        await period.selectOption({ label: 'Last 24 months' });
        await settle(page);
        await expect.poll(() => workedTerritories(page)).toBeGreaterThan(inSixMonths);

        // Remembered for the session
        await visit(page, '/app');
        await expect(page.getByRole('combobox', { name: 'Period' })).toHaveValue('24');
        await page.context().close();
    });
});

test.describe('Territory map', () => {
    test('draws the territories with a legend of the zones', async ({ adminPage }) => {
        await visit(adminPage, '/app');

        const map = adminPage.locator('.leaflet-container');
        await expect(map).toBeVisible();
        await expect(map.locator('path.leaflet-interactive').first()).toBeAttached();
        expect(await map.locator('path.leaflet-interactive').count()).toBeGreaterThan(1);
        await expect(adminPage.getByRole('button', { name: 'Zoom In' })).toBeVisible();

        await expect(adminPage.getByRole('list', { name: 'Legend' }).getByRole('listitem').filter({ hasText: /E2E Zone\s+\(\d+\)/ })).toBeVisible();
    });

    test('colors the territories by their current status', async ({ browser }) => {
        const { page } = await signInAsThrowawayPublisher(browser);
        const legend = page.getByRole('list', { name: 'Legend' });

        await page.getByRole('combobox', { name: 'Color the territories by' }).selectOption({ label: 'Current status' });
        await settle(page);

        await expect(legend.getByRole('listitem').filter({ hasText: /^\s*Available\s+\(\d+\)\s*$/ })).toBeVisible();
        await expect(legend.getByRole('listitem').filter({ hasText: /Not available\s+\(\d+\)/ })).toBeVisible();
        await expect(legend.getByRole('listitem').filter({ hasText: /E2E Zone/ })).toHaveCount(0);
        await page.context().close();
    });
});

test.describe('Top bar and sidebar', () => {
    test('finds a territory with the global search and opens it', async ({ servantPage }) => {
        await visit(servantPage, '/app');

        await servantPage.keyboard.press('Control+k');
        const search = servantPage.getByRole('searchbox', { name: 'Global search' });
        await expect(search).toBeFocused();
        await servantPage.keyboard.type('E2E Free');

        const result = servantPage.getByRole('region', { name: 'Global search results' }).getByRole('link', { name: /^E2E Free \(E2E Zone\)/ });
        await expect(result).toBeVisible();
        await result.click();

        await expect(servantPage).toHaveURL(/\/app\/territories\/\d+/);
        await expect(servantPage.getByRole('heading', { level: 1 })).toContainText('E2E Free');
    });

    test('lists the notifications of the user in the drawer', async ({ browser }) => {
        const { page, id } = await signInAsThrowawayPublisher(browser);
        const title = uniqueName('Notification');
        php(`
            \\Filament\\Notifications\\Notification::make()
                ->title(${JSON.stringify(title)})
                ->body('Sent by the end-to-end tests.')
                ->sendToDatabase(\\App\\Models\\User::find(${id}));

            return true;
        `);

        await visit(page, '/app');
        const bell = page.getByRole('button', { name: /^Notifications, 1 unread notification/ });
        await expect(bell).toBeVisible();
        await bell.click();

        const drawer = page.locator('.fi-modal-window').filter({ visible: true }).last();
        const notification = drawer.getByRole('list', { name: 'Notifications' }).getByRole('listitem').filter({ hasText: title });
        await expect(notification).toBeVisible();
        await expect(notification).toContainText('Sent by the end-to-end tests.');

        await drawer.getByRole('button', { name: 'Mark all as read' }).click();
        await expect(page.getByRole('button', { name: /unread notification/ })).toHaveCount(0);
        await expect.poll(() => php(`return \\App\\Models\\User::find(${id})->unreadNotifications()->count();`)).toBe(0);
        await page.context().close();
    });

    test('collapses and expands the sidebar', async ({ adminPage }) => {
        await visit(adminPage, '/app');
        const sidebar = adminPage.locator('.fi-main-sidebar');
        const expandedWidth = (await sidebar.boundingBox())!.width;

        await adminPage.getByRole('button', { name: 'Collapse sidebar' }).click();
        await expect(adminPage.getByRole('button', { name: 'Expand sidebar' })).toBeVisible();
        await expect.poll(async () => (await sidebar.boundingBox())!.width).toBeLessThan(expandedWidth);

        await adminPage.getByRole('button', { name: 'Expand sidebar' }).click();
        await expect(adminPage.getByRole('button', { name: 'Collapse sidebar' })).toBeVisible();
        await expect.poll(async () => (await sidebar.boundingBox())!.width).toBe(expandedWidth);
    });
});
