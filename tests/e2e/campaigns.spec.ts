import type { Page } from '@playwright/test';
import { php } from './support/artisan';
import { bulkMenuAction, chooseOption, confirmModal, expectNotification, field, modal, row, rowAction, searchTableUntilApplied, settle, visit } from './support/filament';
import { expect, test, uniqueName, users } from './support/fixtures';

/**
 * The campaigns (`app/Filament/Resources/Campaigns`): their list, page, widgets and assignments.
 */

test.describe.configure({ mode: 'parallel' });

/**
 * Create a campaign ending in the given number of days (started a month ago), returning its id.
 */
const createCampaign = (name: string, endsInDays: number): number => php(`
    return \\App\\Models\\Campaign::factory()->createQuietly([
        'name' => ${JSON.stringify(name)},
        'valid_from' => now()->subMonth(),
        'valid_until' => now()->addDays(${endsInDays}),
    ])->id;
`);

/**
 * Create territories in a zone of their own, returning their ids by name; those with an assignee (by email) are
 * assigned in the campaign.
 */
function createTerritories(zoneName: string, territories: { name: string; assignee?: string }[], campaignId?: number): Record<string, number> {
    return php(`
        $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(zoneName)}]);
        $ids = [];

        foreach (json_decode(${JSON.stringify(JSON.stringify(territories))}, true) as $attributes) {
            $territory = \\App\\Models\\Territory::factory()->for($zone)->create(['name' => $attributes['name']]);
            $ids[$territory->name] = $territory->id;

            if (isset($attributes['assignee'])) {
                $territory->assignments()->create([
                    'assignee_id' => \\App\\Models\\User::where('email', $attributes['assignee'])->sole()->id,
                    'campaign_id' => ${campaignId ?? 'null'},
                    'started_at' => now()->subDays(3),
                ]);
            }
        }

        return $ids;
    `);
}

/**
 * A date as the value of a `datetime-local` input, in UTC (the timezone of the tests).
 */
const dateTimeInput = (date: Date): string => date.toISOString().slice(0, 16);

/** The rows of the records of the table (not the individual search row). */
const tableRows = (page: Page) => page.locator('.fi-ta-row:has(.fi-ta-record-checkbox)');

/**
 * Set a filter of the table and apply it.
 */
async function applyFilter(page: Page, label: string, option: string): Promise<void> {
    const apply = page.getByRole('button', { name: 'Apply filters' });

    // Clicked while the page is still loading, the button may not open the panel yet
    await expect(async () => {
        if (!(await apply.isVisible())) {
            await page.getByRole('button', { name: 'Filter', exact: true }).click();
        }

        await expect(apply).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 20_000 });

    await chooseOption(page.locator('.fi-dropdown-panel').filter({ visible: true }), label, option);
    await apply.click();
    await expect(apply).toBeHidden();
    await settle(page);
}

test.describe('Campaigns list', () => {
    test('creates a campaign in the modal, which is listed with its status', async ({ adminPage: page }) => {
        test.slow();
        const name = uniqueName('Campaign');
        const day = 24 * 60 * 60 * 1000;

        await visit(page, '/app/campaigns');
        await page.getByRole('button', { name: 'New campaign' }).click();
        await field(modal(page), 'Name').fill(name);
        await field(modal(page), 'Description').fill('Invite everyone to the memorial');
        await field(modal(page), 'Short Name').fill('MEM');
        const color = modal(page).getByRole('combobox', { name: 'Color' });
        await color.fill('#336699');
        await color.press('Tab');
        await expect(color).toHaveValue('#336699');
        await field(modal(page), 'Valid From').fill(dateTimeInput(new Date(Date.now() - day)));
        await field(modal(page), 'Valid Until').fill(dateTimeInput(new Date(Date.now() + 60 * day)));
        await confirmModal(page, 'Create');

        // Everyone is notified (and emailed) of the new campaign while it is created, which takes a while
        await expect(page.locator('.fi-modal-window').filter({ hasText: 'Create Campaign' })).toBeHidden({ timeout: 150_000 });
        await expectNotification(page, 'Created');
        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText('Active');
        await expect(row(page, name)).toContainText('MEM');
        expect(php(`return \\App\\Models\\Campaign::where('name', ${JSON.stringify(name)})->sole()->only(['short_name', 'color', 'description']);`))
            .toEqual({ short_name: 'MEM', color: '#336699', description: 'Invite everyone to the memorial' });
    });

    test('refuses a campaign ending before it starts', async ({ adminPage: page }) => {
        const name = uniqueName('Backwards Campaign');

        await visit(page, '/app/campaigns');
        await page.getByRole('button', { name: 'New campaign' }).click();
        await field(modal(page), 'Name').fill(name);
        await field(modal(page), 'Valid From').fill('2026-06-10T10:00');
        await field(modal(page), 'Valid Until').fill('2026-06-01T10:00');
        await confirmModal(page, 'Create');

        await expect(modal(page).getByText(/must be a date after/i)).toBeVisible();
        expect(php(`return \\App\\Models\\Campaign::where('name', ${JSON.stringify(name)})->exists();`)).toBe(false);
    });

    test('shows the status of the campaigns', async ({ adminPage: page }) => {
        const prefix = uniqueName('Status');
        createCampaign(`${prefix} Expiring`, 5);
        createCampaign(`${prefix} Ended`, -5);

        await visit(page, '/app/campaigns');
        await searchTableUntilApplied(page, prefix);
        await expect(row(page, `${prefix} Expiring`)).toContainText(/Expiring|Almost Expired/);
        await expect(row(page, `${prefix} Ended`)).toContainText('Terminated');
    });

    test('edits a campaign', async ({ adminPage: page }) => {
        const name = uniqueName('Campaign');
        const id = createCampaign(name, 30);

        await visit(page, '/app/campaigns');
        await searchTableUntilApplied(page, name);
        await expect(tableRows(page)).toHaveCount(1);
        await rowAction(page, name, 'Edit');
        await field(modal(page), 'Name').fill(`${name} edited`);
        await field(modal(page), 'Short Name').fill('EDT');
        await confirmModal(page, 'Save changes');

        await expectNotification(page, 'Saved');
        expect(php(`return \\App\\Models\\Campaign::find(${id})->only(['name', 'short_name']);`)).toEqual({ name: `${name} edited`, short_name: 'EDT' });
    });

    test('deletes a campaign and restores it from the trashed ones', async ({ adminPage: page }) => {
        const name = uniqueName('Campaign');
        const id = createCampaign(name, 30);
        const isTrashed = (): boolean => php(`return \\App\\Models\\Campaign::withTrashed()->find(${id})->trashed();`);

        await visit(page, '/app/campaigns');
        await searchTableUntilApplied(page, name);
        await expect(tableRows(page)).toHaveCount(1);
        await rowAction(page, name, 'Delete');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');
        await expect(tableRows(page)).toHaveCount(0);
        expect(isTrashed()).toBe(true);

        await applyFilter(page, 'Trashed Items', 'Only deleted records');
        await expect(tableRows(page)).toHaveCount(1);
        await rowAction(page, name, 'Restore');
        await confirmModal(page, 'Restore');
        await expectNotification(page, 'Restored');
        expect(isTrashed()).toBe(false);
    });

    test('counts the campaigns about to end in the navigation badge', async ({ adminPage: page }) => {
        createCampaign(uniqueName('Ending Campaign'), 3);
        const badge = page.getByRole('navigation', { name: 'Sidebar navigation' }).getByRole('link', { name: /^Campaigns/ });

        // Other tests may create campaigns meanwhile: compare with the count at the time of the page
        await expect.poll(async () => {
            await visit(page, '/app/campaigns');
            const shown = Number((await badge.innerText()).replace(/\D/g, ''));

            return shown === php<number>('return \\App\\Models\\Campaign::query()->expiring()->count();') && shown > 0;
        }).toBe(true);
    });
});

test.describe('Campaign page', () => {
    test('shows the overview and the chart of the assignments of the campaign', async ({ adminPage: page }) => {
        const name = uniqueName('Campaign');
        const id = createCampaign(name, 20);
        const zone = uniqueName('Campaign Zone');
        createTerritories(zone, [{ name: `${zone} Out`, assignee: users.publisher.email }], id);

        await visit(page, `/app/campaigns/${id}`);

        await expect(page.getByRole('heading', { level: 1, name: `View ${name}` })).toBeVisible();
        const overview = page.locator('.fi-wi-stats-overview');
        await expect(overview).toContainText('Remaining days');
        await expect(overview).toContainText('20');
        await expect(overview).toContainText('Completion status');
        await expect(overview).toContainText(/\d+(\.\d+)?%/);
        // The chart is loaded once scrolled into view
        await page.getByRole('combobox', { name: 'Created By' }).scrollIntoViewIfNeeded();
        await page.mouse.wheel(0, 2000);
        await expect(page.getByRole('heading', { name: 'Assignment status overview' })).toBeVisible();
        await expect(page.locator('.fi-wi-chart canvas')).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Tabs' }).getByRole('link', { name: 'Assignments 1' })).toBeVisible();
    });
});

test.describe('Campaign assignments', () => {
    test('assigns a territory in the campaign with the three steps wizard', async ({ adminPage: page }) => {
        const name = uniqueName('Campaign');
        const id = createCampaign(name, 30);
        const zone = uniqueName('Wizard Zone');
        const territory = `${zone} Free`;
        const ids = createTerritories(zone, [{ name: territory }]);

        await visit(page, `/app/campaigns/${id}/assignments`);
        await expect(page.getByRole('heading', { level: 1, name: 'Manage assignments' })).toBeVisible();
        await page.getByRole('button', { name: 'New territory assignment' }).click();

        const wizard = modal(page);
        await expect(wizard.getByRole('heading', { name: 'Create new assignment' })).toBeVisible();
        await expect(wizard.getByText(`Create a new assignment for campaign "${name}"`)).toBeVisible();
        await chooseOption(wizard, 'Territory', territory);
        await chooseOption(wizard, 'Assigned to', users.servant.name);
        await wizard.getByRole('button', { name: 'Next' }).click();

        await field(wizard, 'Any Notes').fill('Start from the north side');
        await wizard.getByRole('button', { name: 'Next' }).click();

        await expect(wizard.getByText('Review the assignment before creating')).toBeVisible();
        await expect(wizard).toContainText(name);
        await expect(wizard).toContainText(users.servant.name);
        await expect(wizard).toContainText(territory);
        await expect(wizard).toContainText('Start from the north side');
        // The button of the last step of the wizard, not of the footer of the modal
        await wizard.getByRole('button', { name: 'Create', exact: true }).click();

        await expectNotification(page, 'Created');
        await expect(row(page, territory)).toContainText(users.servant.name);
        expect(php(`
            $assignment = \\App\\Models\\TerritoryAssignment::where('territory_id', ${ids[territory]})->sole();

            return [$assignment->campaign_id, $assignment->assignee->email, $assignment->notes, $assignment->ended_at];
        `)).toEqual([id, users.servant.email, 'Start from the north side', null]);
    });

    test('terminates the selected assignments of the campaign', async ({ adminPage: page }) => {
        const name = uniqueName('Campaign');
        const id = createCampaign(name, 30);
        const zone = uniqueName('Terminate Zone');
        const territory = `${zone} One`;
        // A single assignment: the page fails with several of them (see the test below)
        createTerritories(zone, [{ name: territory, assignee: users.publisher.email }], id);

        await visit(page, `/app/campaigns/${id}/assignments`);
        await expect(tableRows(page)).toHaveCount(1);
        await bulkMenuAction(page, [territory], 'Terminate assignments');
        await expect(modal(page).getByRole('heading', { name: 'Terminate selected assignments' })).toBeVisible();
        await confirmModal(page, 'Terminate');

        await expectNotification(page, 'Assignments terminated');
        await expectNotification(page, 'Successfully terminated 1 assignment(s)');
        expect(php(`return \\App\\Models\\TerritoryAssignment::where('campaign_id', ${id})->whereNull('ended_at')->count();`)).toBe(0);
        // Only the assignments in progress are listed by default
        await expect(tableRows(page)).toHaveCount(0);
    });

    // Bug: the assignments page of a campaign with several assignments in progress fails with a
    // LazyLoadingViolationException (outside production, where models are strict): the table hides its campaign column,
    // so the campaign is no longer eager loaded, but `TerritoryAssignment::isOverdue()` (called by the row classes of
    // `TerritoryAssignmentResource::table()`) reads it for every row.
    test.fixme('lists the assignments of a campaign that has several in progress', async ({ adminPage: page }) => {
        const id = createCampaign(uniqueName('Campaign'), 30);
        const zone = uniqueName('Several Zone');
        const territories = [`${zone} One`, `${zone} Two`];
        createTerritories(zone, territories.map((territory) => ({ name: territory, assignee: users.publisher.email })), id);

        await visit(page, `/app/campaigns/${id}/assignments`);

        await expect(tableRows(page)).toHaveCount(2);
    });
});
