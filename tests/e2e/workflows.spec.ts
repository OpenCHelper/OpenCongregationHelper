import type { Browser, Locator, Page } from '@playwright/test';
import { appURL } from '../../playwright.config';
import { php } from './support/artisan';
import {
    bulkMenuAction,
    chooseOption,
    confirmModal,
    expectNotification,
    field,
    labelled,
    modal,
    row,
    rowMenuAction,
    searchTableUntilApplied,
    visit,
} from './support/filament';
import { expect, logIn, test, uniqueName, users } from './support/fixtures';
import { linkInMailsTo } from './support/mail';

/**
 * The whole workflows of the users, from start to end and through the pages they use, as documented in
 * `docs/user-documentation/en/`: they chain the features that the other specs test one by one.
 *
 * Every workflow creates the records it works on, with names of its own.
 */

test.describe.configure({ mode: 'parallel' });

/** The rows of the records of a table (not its individual search row). */
const tableRows = (page: Page): Locator => page.locator('.fi-ta-row:has(.fi-ta-record-checkbox)');

/** A tab of the pages of a record (e.g. the territories of a zone), with its count, in the page (not the sidebar). */
const recordTab = (page: Page, name: string): Locator =>
    page.getByRole('main').getByRole('link', { name: new RegExp(`^${name}( \\d+)?$`) }).first();

/**
 * Create a user who can sign in (password `password`), with the role, returning their name and email.
 */
function createUser(role: string): { name: string; email: string } {
    const firstName = uniqueName('Workflow').replace(/\s+/g, '');

    return php(`
        $user = \\App\\Models\\User::factory()->verified()->create([
            'first_name' => ${JSON.stringify(firstName)},
            'last_name' => 'Tester',
            'email' => strtolower(${JSON.stringify(firstName)}).'@e2e.test',
            'locale' => 'en',
        ]);
        $user->assignRole(${JSON.stringify(role)});

        return ['name' => $user->full_name, 'email' => $user->email];
    `);
}

/**
 * Sign in in a browser of one's own.
 */
async function signIn(browser: Browser, email: string, password = 'password'): Promise<Page> {
    const page = await (await browser.newContext({ baseURL: appURL })).newPage();
    await logIn(page, email, password);
    await expect(page).not.toHaveURL(/\/login/);

    return page;
}

/**
 * Open the database notifications from the top bar, and follow the "View" link of the one with the title.
 */
async function openNotification(page: Page, title: string): Promise<void> {
    await visit(page, '/app');
    await page.getByRole('button', { name: /^Notifications/ }).click();
    const notification = page.locator('.fi-no-notification').filter({ hasText: title }).first();
    await expect(notification).toBeVisible();
    await notification.getByRole('link', { name: 'View' }).click();
}

test.describe('Territory workflow', () => {
    test('a zone and a territory are created, then the territory is assigned, worked and returned', async ({ browser, servantPage: servant }) => {
        const publisher = createUser('publisher');
        const zone = uniqueName('Workflow Zone');
        const territory = `${zone} Main Street`;

        // 1. The territory servant creates a zone…
        await visit(servant, '/app/territory-zones/create');
        await field(servant, 'Name').fill(zone);
        await servant.getByRole('button', { name: 'Create', exact: true }).and(servant.locator('[type="submit"]')).click();
        await expectNotification(servant, 'Created');
        await expect(servant.getByRole('heading', { level: 1 })).toContainText(zone);

        // 2. …then a territory in it, from the territories of the zone
        await recordTab(servant, 'Territories').click();
        await expect(servant.getByRole('heading', { level: 1, name: `Territories of zone ${zone}` })).toBeVisible();
        await servant.getByRole('button', { name: 'New territory' }).click();
        await field(modal(servant), 'Name').fill(territory);
        await chooseOption(modal(servant), 'Zone', zone);
        await field(modal(servant), 'Notes').fill('Start from the church');
        await confirmModal(servant, 'Create');
        await expectNotification(servant, 'Created');
        await expect(row(servant, territory)).toBeVisible();

        // 3. …and assigns it to a publisher, from the page of the territory
        await visit(servant, '/app/territories');
        await searchTableUntilApplied(servant, territory);
        await row(servant, territory).getByRole('link', { name: territory }).first().click();
        await expect(servant.getByRole('heading', { level: 1, name: `View ${territory}` })).toBeVisible();
        await servant.locator('.fi-header').getByRole('button', { name: 'Assign Territory' }).click();
        await chooseOption(modal(servant), 'Assigned to', publisher.name);
        await confirmModal(servant, 'Create');
        await expectNotification(servant, 'Territory assigned');

        // 4. The publisher is notified, opens the assignment, works the territory and asks to return it
        const publisherPage = await signIn(browser, publisher.email);
        await openNotification(publisherPage, `Territory ${territory} assigned`);
        await expect(publisherPage).toHaveURL(/\/app\/territory-assignments\/\d+$/);
        await expect(publisherPage.getByText(territory).first()).toBeVisible();
        await publisherPage.locator('.fi-header').getByRole('button', { name: 'Ask to return' }).click();
        await confirmModal(publisherPage, 'Ask to return');
        await expectNotification(publisherPage, 'Return requested');
        await publisherPage.context().close();

        // 5. The territory servant is notified of the request, and ends the assignment
        await openNotification(servant, `Return of territory ${territory} requested`);
        await expect(servant.getByRole('heading', { name: /Return requested on / })).toBeVisible();
        await servant.locator('.fi-sc-callout, [class*="callout"]').filter({ hasText: 'Return requested on' })
            .getByRole('button', { name: 'End Assignment' }).click();
        await confirmModal(servant, 'End');
        await expectNotification(servant, 'Assignment returned');

        // 6. The territory is free again, with the assignment in its history
        const id = php<number>(`return \\App\\Models\\Territory::where('name', ${JSON.stringify(territory)})->sole()->id;`);
        await visit(servant, `/app/territories/${id}`);
        await expect(servant.locator('.fi-header').getByRole('button', { name: 'Assign Territory' })).toBeVisible();
        expect(php(`
            return \\App\\Models\\TerritoryAssignment::with('assignee')->where('territory_id', ${id})->get()
                ->map(fn ($assignment) => [$assignment->assignee->full_name, $assignment->return_requested_at !== null, $assignment->ended_at !== null])
                ->all();
        `)).toEqual([[publisher.name, true, true]]);
    });
});

test.describe('Campaign workflow', () => {
    test('a campaign is created, its territories are assigned in it, then its assignments are terminated', async ({ adminPage: admin }) => {
        // Everyone is notified (and emailed) of a new campaign while it is created, which takes a while
        test.slow();
        const campaign = uniqueName('Workflow Campaign');
        const zone = uniqueName('Campaign Zone');
        const territory = `${zone} Market`;
        php(`
            $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(zone)}]);
            \\App\\Models\\Territory::factory()->for($zone)->create(['name' => ${JSON.stringify(territory)}]);

            return true;
        `);

        // 1. The campaign is created, from today for two months
        const day = 24 * 60 * 60 * 1000;
        const dateTimeInput = (date: Date): string => date.toISOString().slice(0, 16);
        await visit(admin, '/app/campaigns');
        await admin.getByRole('button', { name: 'New campaign' }).click();
        await field(modal(admin), 'Name').fill(campaign);
        await field(modal(admin), 'Short Name').fill('WF');
        await field(modal(admin), 'Valid From').fill(dateTimeInput(new Date(Date.now() - day)));
        await field(modal(admin), 'Valid Until').fill(dateTimeInput(new Date(Date.now() + 60 * day)));
        await confirmModal(admin, 'Create');
        await expect(admin.locator('.fi-modal-window').filter({ hasText: 'Create Campaign' })).toBeHidden({ timeout: 150_000 });
        await expectNotification(admin, 'Created');

        // 2. A territory is assigned in the campaign, with the wizard of its assignments
        await searchTableUntilApplied(admin, campaign);
        await expect(row(admin, campaign)).toContainText('Active');
        await row(admin, campaign).getByRole('link', { name: campaign }).first().click();
        await recordTab(admin, 'Assignments').click();
        await admin.getByRole('button', { name: 'New territory assignment' }).click();
        const wizard = modal(admin);
        await chooseOption(wizard, 'Territory', territory);
        await chooseOption(wizard, 'Assigned to', users.group.name);
        await wizard.getByRole('button', { name: 'Next' }).click();
        await wizard.getByRole('button', { name: 'Next' }).click();
        await expect(wizard).toContainText(campaign);
        await wizard.getByRole('button', { name: 'Create', exact: true }).click();
        await expectNotification(admin, 'Created');
        await expect(tableRows(admin)).toHaveCount(1);
        await expect(row(admin, territory)).toContainText(users.group.name);

        // 3. The territory shows the campaign of its assignment
        const territoryId = php<number>(`return \\App\\Models\\Territory::where('name', ${JSON.stringify(territory)})->sole()->id;`);
        await visit(admin, '/app/territories');
        await searchTableUntilApplied(admin, territory);
        await expect(row(admin, territory)).toContainText(users.group.name);

        // 4. At the end of the campaign, its assignments are terminated
        await visit(admin, '/app/campaigns');
        await searchTableUntilApplied(admin, campaign);
        await row(admin, campaign).getByRole('link', { name: campaign }).first().click();
        await recordTab(admin, 'Assignments').click();
        await bulkMenuAction(admin, [territory], 'Terminate assignments');
        await confirmModal(admin, 'Terminate');
        await expectNotification(admin, 'Assignments terminated');
        await expect(tableRows(admin)).toHaveCount(0);

        expect(php(`
            $assignment = \\App\\Models\\TerritoryAssignment::with('campaign')->where('territory_id', ${territoryId})->sole();

            return [$assignment->campaign->name, $assignment->ended_at !== null];
        `)).toEqual([campaign, true]);
    });
});

test.describe('Onboarding workflow', () => {
    test('a group overseer is invited, chooses their password, signs in and assigns a territory', async ({ adminPage: admin, browser }) => {
        const firstName = uniqueName('Overseer').replace(/\s+/g, '');
        const email = `${firstName.toLowerCase()}@e2e.test`;
        const zone = uniqueName('Group Zone');
        const territory = `${zone} Park Avenue`;
        php(`
            $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(zone)}]);
            \\App\\Models\\Territory::factory()->for($zone)->create(['name' => ${JSON.stringify(territory)}]);

            return true;
        `);

        // 1. The administrator creates the user, with the group overseer role and access to the app
        await visit(admin, '/app/users/create');
        await field(admin, 'First Name').fill(firstName);
        await field(admin, 'Last Name').fill('Group');
        await field(admin, 'Email').fill(email);
        // A live field: checked again if Livewire re-rendered it unchecked (the page was not ready yet)
        await expect(async () => {
            await admin.getByRole('checkbox', { name: 'Allow access' }).check();
            await expect(admin.getByText('They will receive an email to choose their password.')).toBeVisible({ timeout: 2_000 });
        }).toPass();
        await admin.locator('.fi-fo-field').filter({ has: admin.locator('label', { hasText: labelled('Roles') }) })
            .locator('.fi-select-input-btn').click();
        await admin.getByRole('option', { name: 'group_overseer', exact: true }).filter({ visible: true }).first().click();
        await admin.keyboard.press('Escape');
        await admin.getByRole('button', { name: 'Create', exact: true }).click();
        await expectNotification(admin, `An invitation to choose the password has been sent to ${email}.`);

        // 2. The new user follows the link of the invitation and chooses a password
        const password = 'Workflow-Password-2026!';
        // Searched among the emails of the address: other emails (e.g. a new campaign) may have reached it since
        let invitation: string | null = null;
        await expect.poll(() => (invitation = linkInMailsTo(email, /\/password-reset\/reset/))).not.toBeNull();
        const invited = await (await browser.newContext({ baseURL: appURL })).newPage();
        await visit(invited, invitation!);
        await expect(invited.getByRole('heading', { name: 'Choose your password' })).toBeVisible();
        await field(invited, 'Password').fill(password);
        await field(invited, 'Confirm password').fill(password);
        // Clicked again if the first click was lost (sent before the form was ready): the password is not set then
        await expect(async () => {
            await invited.getByRole('button', { name: 'Set password' }).click();
            await expect(invited.locator('.fi-no-notification').filter({ hasText: 'Your password has been set. You can now sign in.' }).first())
                .toBeVisible({ timeout: 10_000 });
        }).toPass({ timeout: 40_000 });
        await invited.context().close();

        // 3. They sign in, and assign a territory to a publisher of their group
        const overseer = await signIn(browser, email, password);
        await visit(overseer, '/app/territories');
        await searchTableUntilApplied(overseer, territory);
        await rowMenuAction(overseer, territory, 'Assign Territory');
        await chooseOption(modal(overseer), 'Assigned to', users.publisher.name);
        await confirmModal(overseer, 'Create');
        await expectNotification(overseer, 'Territory assigned');

        // 4. The assignment is listed among the assignments, created by them
        await visit(overseer, '/app/territory-assignments');
        await searchTableUntilApplied(overseer, territory);
        await expect(row(overseer, territory)).toContainText(users.publisher.name);
        expect(php(`
            $assignment = \\App\\Models\\TerritoryAssignment::with('assignee', 'creator')
                ->whereHas('territory', fn ($query) => $query->where('name', ${JSON.stringify(territory)}))->sole();

            return [$assignment->assignee->email, $assignment->creator->email];
        `)).toEqual([users.publisher.email, email]);
        await overseer.context().close();
    });
});
