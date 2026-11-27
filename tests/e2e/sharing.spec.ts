import type { Browser, Page } from '@playwright/test';
import { appURL } from '../../playwright.config';
import { php } from './support/artisan';
import { confirmModal, expectNotification, headerMenuAction, modal, visit } from './support/filament';
import { expect, test, uniqueName, users } from './support/fixtures';

/**
 * Sharing an assignment through a public link (the `share` action of the assignment page), and the guest pages that
 * the link opens (`app/Filament/Guest/Pages`). The e2e environment shares the personal data and lets the guests ask to
 * return the territory (`ENABLE_SHARED_PII`, `GUEST_ALLOW_RETURNING_ASSIGNMENT`): the settings are never changed here.
 */

test.describe.configure({ mode: 'parallel' });

/**
 * Create a territory with a map, assigned to the publisher, returning the ids of the territory and of its assignment.
 */
function createAssignment(name: string): { territoryId: number; assignmentId: number } {
    return php(`
        $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(`${name} zone`)}, 'color' => '#3366cc']);
        $territory = \\App\\Models\\Territory::factory()->withMap()->for($zone)->create(['name' => ${JSON.stringify(name)}, 'notes' => 'Shared notes']);
        $assignment = $territory->assignments()->create([
            'assignee_id' => \\App\\Models\\User::where('email', ${JSON.stringify(users.publisher.email)})->sole()->id,
            'started_at' => now()->subDays(2),
        ]);

        return ['territoryId' => $territory->id, 'assignmentId' => $assignment->id];
    `);
}

/**
 * Share the assignment from its page, returning the URL of the link.
 */
async function shareAssignment(page: Page, assignmentId: number): Promise<string> {
    await visit(page, `/app/territory-assignments/${assignmentId}`);
    await headerMenuAction(page, 'Actions', 'Share');

    const shareModal = modal(page);
    await expect(shareModal.getByRole('heading', { name: 'Share Assignment' })).toBeVisible();
    await expect(shareModal.getByText('This assignment is not shared yet')).toBeVisible();
    await shareModal.getByRole('button', { name: 'Create link' }).click();

    const url = shareModal.getByRole('textbox', { name: 'Sharing URL' });
    await expect(url).toHaveValue(/\/share\/[0-9a-f-]{36}$/);

    return url.inputValue();
}

/**
 * Open a page in a browser context of its own, signed in as nobody, on the device of the project (e.g. a phone).
 */
async function guestPage(browser: Browser): Promise<Page> {
    const { viewport, userAgent, isMobile, hasTouch, deviceScaleFactor } = test.info().project.use;
    const context = await browser.newContext({ baseURL: appURL, viewport, userAgent, isMobile, hasTouch, deviceScaleFactor });

    return context.newPage();
}

const sharedUrlOf = (assignmentId: number): string | null => php(`
    return \\App\\Models\\TerritoryAssignment::find(${assignmentId})->shared?->uid;
`);

test.describe('Sharing an assignment', () => {
    test('creates a link, copies it, and opens the shared page for guests', async ({ adminPage: page, browser }) => {
        const name = uniqueName('Shared Territory');
        const { assignmentId } = createAssignment(name);
        await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);

        const url = await shareAssignment(page, assignmentId);
        const uid = url.split('/share/')[1];
        expect(sharedUrlOf(assignmentId)).toBe(uid);

        await modal(page).getByRole('button', { name: 'Copy URL' }).click();
        await expectNotification(page, 'URL copied to clipboard');
        await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toBe(url);

        const guest = await guestPage(browser);
        await guest.goto(url);
        await expect(guest).toHaveURL(new RegExp(`/guest/shared-assignment/${uid}$`));
        await expect(guest.getByRole('heading', { level: 1, name: `Assignment: ${name}` })).toBeVisible();
        await expect(guest.getByText(`${name} zone`)).toBeVisible();
        await expect(guest.getByText(users.publisher.name)).toBeVisible();
        await expect(guest.getByText('Shared notes')).toBeVisible();
        await expect(guest.locator('.leaflet-overlay-pane path').first()).toBeVisible();
        await guest.context().close();
    });

    test('lets the guests ask to return the territory', async ({ adminPage: page, browser }) => {
        const name = uniqueName('Returned Territory');
        const { assignmentId } = createAssignment(name);
        const url = await shareAssignment(page, assignmentId);

        const guest = await guestPage(browser);
        await guest.goto(url);
        await guest.getByRole('button', { name: 'Ask to return' }).click();
        await expect(modal(guest).getByRole('heading', { name: 'Ask to return the territory?' })).toBeVisible();
        await confirmModal(guest, 'Ask to return');

        await expectNotification(guest, 'Return requested');
        await expect(guest.getByRole('heading', { name: /Return requested on / })).toBeVisible();
        expect(php(`return \\App\\Models\\TerritoryAssignment::find(${assignmentId})->return_requested_at !== null;`)).toBe(true);
        await guest.context().close();
    });

    test('stops sharing: the link no longer opens', async ({ adminPage: page, browser }) => {
        const name = uniqueName('Unshared Territory');
        const { assignmentId } = createAssignment(name);
        const url = await shareAssignment(page, assignmentId);
        const uid = url.split('/share/')[1];

        // The modal shows the existing link when opened again
        await visit(page, `/app/territory-assignments/${assignmentId}`);
        await headerMenuAction(page, 'Actions', 'Share');
        await expect(modal(page).getByRole('textbox', { name: 'Sharing URL' })).toHaveValue(url);
        await modal(page).getByRole('button', { name: 'Stop Sharing' }).click();
        await expect(modal(page).getByRole('heading', { name: 'Confirm Stop Sharing' })).toBeVisible();
        await confirmModal(page, 'Confirm');

        await expectNotification(page, 'Sharing stopped successfully');
        expect(sharedUrlOf(assignmentId)).toBeNull();

        const guest = await guestPage(browser);
        expect((await guest.request.get(url, { maxRedirects: 0 })).status()).toBe(404);
        expect((await guest.request.get(`/guest/shared-assignment/${uid}`)).status()).toBe(404);
        await guest.context().close();
    });

    test('shows the shared assignment on the generic shared resource page too', async ({ adminPage: page, browser }) => {
        const name = uniqueName('Resource Territory');
        const { assignmentId } = createAssignment(name);
        const uid = (await shareAssignment(page, assignmentId)).split('/share/')[1];

        const guest = await guestPage(browser);
        await guest.goto(`/guest/shared-resource/${uid}`);
        await expect(guest.getByRole('heading', { level: 1, name: `Assignment: ${name}` })).toBeVisible();
        await expect(guest.getByText(users.publisher.name)).toBeVisible();
        await guest.context().close();
    });

    test('opens the shared link of an assignment on a phone @mobile', async ({ adminPage: page, browser }) => {
        const name = uniqueName('Phone Territory');
        const { assignmentId } = createAssignment(name);
        const url = await shareAssignment(page, assignmentId);
        await modal(page).getByRole('button', { name: 'Close' }).first().click();

        await expect(page.getByText(name).first()).toBeVisible();
        await expect(page.getByText(users.publisher.name).first()).toBeVisible();

        const guest = await guestPage(browser);
        await guest.goto(url);
        await expect(guest.getByRole('heading', { level: 1, name: `Assignment: ${name}` })).toBeVisible();
        await expect(guest.locator('.leaflet-overlay-pane path').first()).toBeVisible();
        await expect(guest.getByRole('button', { name: 'Ask to return' })).toBeVisible();
        await guest.context().close();
    });
});
