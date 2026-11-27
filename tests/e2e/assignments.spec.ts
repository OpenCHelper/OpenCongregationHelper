import type { Browser, Page } from '@playwright/test';
import { appURL } from '../../playwright.config';
import { php } from './support/artisan';
import { bulkMenuAction, chooseOption, confirmModal, expectNotification, field, modal, row, rowMenuAction, searchTableUntilApplied, settle, visit } from './support/filament';
import { expect, logIn, test, uniqueName, users } from './support/fixtures';

/**
 * The assignments of the territories (`app/Filament/Resources/TerritoryAssignments`), and the actions assigning and
 * returning the territories.
 *
 * Every test assigns territories of a zone of its own, to people of its own when it needs to sign in as them.
 */

test.describe.configure({ mode: 'parallel' });

type Arranged = { zoneId: number; ids: Record<string, number>; assignments: Record<string, number> };

/**
 * Create a zone with territories, assigning those that have an assignee (by email) to them, in a campaign when given
 * one (by id).
 */
function arrange(zoneName: string, territories: { name: string; assignee?: string; campaignId?: number }[]): Arranged {
    return php(`
        $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(zoneName)}]);
        $ids = [];
        $assignments = [];

        foreach (json_decode(${JSON.stringify(JSON.stringify(territories))}, true) as $attributes) {
            $territory = \\App\\Models\\Territory::factory()->withMap()->for($zone)->create(['name' => $attributes['name']]);
            $ids[$territory->name] = $territory->id;

            if (isset($attributes['assignee'])) {
                $assignments[$territory->name] = $territory->assignments()->create([
                    'assignee_id' => \\App\\Models\\User::where('email', $attributes['assignee'])->sole()->id,
                    'campaign_id' => $attributes['campaignId'] ?? null,
                    'started_at' => now()->subDays(7),
                ])->id;
            }
        }

        return ['zoneId' => $zone->id, 'ids' => $ids, 'assignments' => $assignments];
    `);
}

/**
 * Create a user who can sign in, with the role, returning their id, name and email (their password is `password`).
 */
function createUser(role: string): { id: number; name: string; email: string } {
    const firstName = uniqueName('Pat').replace(/\s+/g, '');

    return php(`
        $user = \\App\\Models\\User::factory()->create([
            'first_name' => ${JSON.stringify(firstName)},
            'last_name' => 'Tester',
            'email' => strtolower(${JSON.stringify(firstName)}).'@e2e.test',
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);
        $user->assignRole(${JSON.stringify(role)});

        return ['id' => $user->id, 'name' => $user->full_name, 'email' => $user->email];
    `);
}

/**
 * Sign in as a user in a browser context of their own.
 */
async function signIn(browser: Browser, email: string): Promise<Page> {
    const context = await browser.newContext({ baseURL: appURL });
    const page = await context.newPage();
    await logIn(page, email, 'password');
    await expect(page).not.toHaveURL(/\/login/);

    return page;
}

/**
 * The assignment in progress of a territory, if any: its id, assignee and campaign.
 */
const currentAssignmentOf = (territoryId: number): { id: number; assignee: string; campaign: string | null } | null => php(`
    $assignment = \\App\\Models\\TerritoryAssignment::with('assignee', 'campaign')->where('territory_id', ${territoryId})->whereNull('ended_at')->first();

    return $assignment ? ['id' => $assignment->id, 'assignee' => $assignment->assignee->full_name, 'campaign' => $assignment->campaign?->name] : null;
`);

/**
 * Create a campaign of the given validity, returning its id.
 */
const createCampaign = (name: string, validFrom: string, validUntil: string): number => php(`
    return \\App\\Models\\Campaign::factory()->createQuietly(['name' => ${JSON.stringify(name)}, 'valid_from' => ${validFrom}, 'valid_until' => ${validUntil}])->id;
`);

/** The rows of the records of the table (not the individual search row). */
const tableRows = (page: Page) => page.locator('.fi-ta-row:has(.fi-ta-record-checkbox)');

test.describe('Assigning territories', () => {
    test('assigns a free territory from the territories table, in a campaign', async ({ adminPage: page }) => {
        const zone = uniqueName('Assign Zone');
        const name = `${zone} Free`;
        const campaign = uniqueName('Assign Campaign');
        const { ids } = arrange(zone, [{ name }]);
        createCampaign(campaign, 'now()->subDay()', 'now()->addMonths(2)');

        await visit(page, '/app/territories');
        await searchTableUntilApplied(page, name);
        await rowMenuAction(page, name, 'Assign Territory');
        await expect(modal(page).getByRole('heading', { name: 'Create new assignment' })).toBeVisible();
        await chooseOption(modal(page), 'Assigned to', users.servant.name);
        await chooseOption(modal(page), 'Campaign', campaign);
        await confirmModal(page, 'Create');

        await expectNotification(page, 'Territory assigned');
        await expect(row(page, name)).toContainText(users.servant.name);
        expect(currentAssignmentOf(ids[name])).toMatchObject({ assignee: users.servant.name, campaign });
    });

    test('assigns a free territory from its page @mobile', async ({ adminPage: page }) => {
        const zone = uniqueName('Assign Zone');
        const name = `${zone} Free`;
        const { ids } = arrange(zone, [{ name }]);

        await visit(page, `/app/territories/${ids[name]}`);
        await page.locator('.fi-header').getByRole('button', { name: 'Assign Territory' }).click();
        await chooseOption(modal(page), 'Assigned to', users.publisher.name);
        await confirmModal(page, 'Create');

        await expectNotification(page, 'Territory assigned');
        // The header actions only follow the new assignment once the page is reloaded
        await visit(page, `/app/territories/${ids[name]}`);
        await expect(page.locator('.fi-header').getByRole('button', { name: 'End Assignment' })).toBeVisible();
        await expect(page.locator('.fi-header').getByRole('button', { name: 'Assign Territory' })).toHaveCount(0);
        expect(currentAssignmentOf(ids[name])).toMatchObject({ assignee: users.publisher.name, campaign: null });
    });

    test('ends the assignment of a territory from the territories table', async ({ adminPage: page }) => {
        const zone = uniqueName('Cancel Zone');
        const name = `${zone} Out`;
        const { ids } = arrange(zone, [{ name, assignee: users.publisher.email }]);

        await visit(page, '/app/territories');
        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText(users.publisher.name);
        await rowMenuAction(page, name, 'End Assignment');
        await expect(modal(page).getByRole('button', { name: 'Assigned to' })).toBeDisabled();
        await confirmModal(page, 'End');

        await expectNotification(page, 'Territory returned successfully');
        await expect(row(page, name)).toContainText('Ended today');
        expect(currentAssignmentOf(ids[name])).toBeNull();
    });

    test('assigns several territories at once, then ends their assignments at once', async ({ adminPage: page }) => {
        const zone = uniqueName('Bulk Zone');
        const names = [`${zone} One`, `${zone} Two`];
        const { ids } = arrange(zone, names.map((name) => ({ name })));

        await visit(page, '/app/territories');
        await searchTableUntilApplied(page, zone);
        await expect(tableRows(page)).toHaveCount(2);
        await bulkMenuAction(page, names, 'Assign Massively');
        await chooseOption(modal(page), 'Assigned to', users.group.name);
        await confirmModal(page, /submit|confirm/i);

        await expectNotification(page, '2 territories assigned');
        for (const name of names) {
            await expect(row(page, name)).toContainText(users.group.name);
            expect(currentAssignmentOf(ids[name])).toMatchObject({ assignee: users.group.name });
        }

        await bulkMenuAction(page, names, 'End Assignments');
        await confirmModal(page, /confirm/i);

        await expectNotification(page, '2 territories returned');
        for (const name of names) {
            expect(currentAssignmentOf(ids[name])).toBeNull();
        }
    });
});

test.describe('Assignments list', () => {
    test('shows the assignments of ended campaigns in their tab, with a banner', async ({ adminPage: page }) => {
        const zone = uniqueName('Overdue Zone');
        const name = `${zone} Late`;
        const campaign = uniqueName('Ended Campaign');
        const campaignId = createCampaign(campaign, 'now()->subMonths(2)', 'now()->addMonth()');
        arrange(zone, [{ name, assignee: users.publisher.email, campaignId }]);
        // The campaign ends once the territory is assigned: an assignment cannot start in a terminated campaign
        php(`\\App\\Models\\Campaign::query()->whereKey(${campaignId})->update(['valid_until' => now()->subWeek()]); return true;`);

        await visit(page, '/app/territory-assignments');
        const tabs = page.getByRole('group', { name: 'Filter records' });
        await expect(tabs.getByRole('button', { name: 'All' })).toBeVisible();
        await expect(tabs.getByRole('button', { name: /^Campaign ending soon/ })).toBeVisible();
        await expect(page.getByRole('heading', { name: /still out for a campaign that has ended/ })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Return all' })).toBeVisible();

        await tabs.getByRole('button', { name: /^Campaign ended \d+$/ }).click();
        await settle(page);
        await expect(row(page, 'E2E Overdue')).toBeVisible();
        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText(`Campaign ended 7 days ago`);

        await tabs.getByRole('button', { name: /^Campaign ending soon/ }).click();
        await settle(page);
        await expect(tableRows(page)).toHaveCount(0);
    });

    test('returns an assignment from the list', async ({ adminPage: page }) => {
        const zone = uniqueName('Return Zone');
        const name = `${zone} Done`;
        const { assignments } = arrange(zone, [{ name, assignee: users.publisher.email }]);

        await visit(page, '/app/territory-assignments');
        await searchTableUntilApplied(page, name);
        await row(page, name).getByRole('button', { name: 'End Assignment' }).click();
        await confirmModal(page, 'End');

        await expectNotification(page, 'Territory returned successfully');
        // Only the assignments in progress are listed by default
        await expect(tableRows(page)).toHaveCount(0);
        expect(php(`return \\App\\Models\\TerritoryAssignment::find(${assignments[name]})->ended_at !== null;`)).toBe(true);

        await visit(page, `/app/territory-assignments/${assignments[name]}`);
        await expect(page.getByText('Assignment End')).toBeVisible();
        await expect(page.locator('.fi-header').getByRole('button', { name: 'End Assignment' })).toHaveCount(0);
    });

    test('edits the notes of an assignment', async ({ adminPage: page }) => {
        const zone = uniqueName('Edit Zone');
        const name = `${zone} Noted`;
        const { assignments } = arrange(zone, [{ name, assignee: users.publisher.email }]);

        await visit(page, '/app/territory-assignments');
        await searchTableUntilApplied(page, name);
        await rowMenuAction(page, name, 'Edit');
        await expect(modal(page).getByRole('button', { name: 'Territory' })).toBeDisabled();
        await field(modal(page), 'Any Notes').fill('Keys at the reception');
        await confirmModal(page, 'Save changes');

        await expectNotification(page, 'Saved');
        expect(php(`return \\App\\Models\\TerritoryAssignment::find(${assignments[name]})->notes;`)).toBe('Keys at the reception');
    });
});

test.describe('Returning on request', () => {
    test('the assignee asks to return their territory, then the servant is notified and returns it', async ({ browser, servantPage }) => {
        const publisher = createUser('publisher');
        const zone = uniqueName('Request Zone');
        const name = `${zone} Asked`;
        const { assignments } = arrange(zone, [{ name, assignee: publisher.email }]);
        const assignmentId = assignments[name];

        const publisherPage = await signIn(browser, publisher.email);
        await visit(publisherPage, `/app/territory-assignments/${assignmentId}`);
        await expect(publisherPage.getByText(name).first()).toBeVisible();
        await publisherPage.locator('.fi-header').getByRole('button', { name: 'Ask to return' }).click();
        await expect(modal(publisherPage).getByRole('heading', { name: 'Ask to return the territory?' })).toBeVisible();
        await confirmModal(publisherPage, 'Ask to return');

        await expectNotification(publisherPage, 'Return requested');
        await expect(publisherPage.getByRole('heading', { name: /Return requested on / })).toBeVisible();
        await expect(publisherPage.locator('.fi-header').getByRole('button', { name: 'Ask to return' })).toHaveCount(0);
        expect(php(`return \\App\\Models\\TerritoryAssignment::find(${assignmentId})->return_requested_at !== null;`)).toBe(true);

        // The territory department is notified (in the language of the servant, which the seeder picks at random)
        expect(php(`
            return \\App\\Models\\User::where('email', ${JSON.stringify(users.servant.email)})->sole()->notifications()->get()
                ->contains(fn ($notification) => str_contains($notification->data['title'] ?? '', ${JSON.stringify(name)})
                    && str_ends_with($notification->data['actions'][0]['url'] ?? '', '/app/territory-assignments/${assignmentId}'));
        `)).toBe(true);
        await visit(servantPage, '/app');
        await servantPage.getByRole('button', { name: /^Notifications/ }).click();
        await expect(servantPage.getByRole('dialog').getByText(name).first()).toBeVisible();
        await servantPage.keyboard.press('Escape');

        await visit(servantPage, `/app/territory-assignments/${assignmentId}`);
        await expect(servantPage.getByRole('heading', { name: /Return requested on / })).toBeVisible();
        await servantPage.locator('.fi-sc-callout, [class*="callout"]').filter({ hasText: 'Return requested on' })
            .getByRole('button', { name: 'End Assignment' }).click();
        await confirmModal(servantPage, 'End');

        await expectNotification(servantPage, 'Assignment returned');
        await expect(servantPage.getByText('Assignment End')).toBeVisible();
        expect(php(`return \\App\\Models\\TerritoryAssignment::find(${assignmentId})->ended_at !== null;`)).toBe(true);
        await publisherPage.context().close();
    });
});

test.describe('Assignments of a user', () => {
    test('lists the assignments of a user on their page', async ({ adminPage: page }) => {
        const user = createUser('publisher');
        const zone = uniqueName('User Zone');
        arrange(zone, [{ name: `${zone} First`, assignee: user.email }, { name: `${zone} Second`, assignee: user.email }, { name: `${zone} Other` }]);

        await visit(page, `/app/users/${user.id}/assignments`);

        await expect(page.getByRole('heading', { level: 1, name: `Assignments of ${user.name}` })).toBeVisible();
        await expect(tableRows(page)).toHaveCount(2);
        await expect(row(page, `${zone} First`)).toContainText('(in progress)');
        await expect(row(page, `${zone} Second`)).toBeVisible();
    });
});
