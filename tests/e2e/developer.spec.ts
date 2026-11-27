import { test, expect, pageAs, uniqueName, users } from './support/fixtures';
import { php } from './support/artisan';
import { confirmModal, expectNotification, field, modal, row, rowAction, searchTable, visit } from './support/filament';
import type { Role } from './support/users';

/**
 * A throwaway user, who has not chosen their password yet (so that they can be sent an invitation).
 */
function createInvitedUser(): { id: number; email: string; name: string } {
    const name = uniqueName('Mailed');
    const email = `${name.toLowerCase().replaceAll(' ', '-')}@e2e.test`;
    const id = php<number>(`
        return \\App\\Models\\User::factory()->create([
            'first_name' => ${JSON.stringify(name)},
            'last_name' => 'Developer',
            'email' => ${JSON.stringify(email)},
            'password' => null,
            'locale' => 'en',
        ])->assignRole('publisher')->id;
    `);

    return { id, email, name };
}

test.describe('Log Viewer', () => {
    test('the administrator opens the log viewer, with a tab per level', async ({ adminPage }) => {
        await visit(adminPage, '/app/logs');

        await expect(adminPage.getByRole('heading', { name: 'Log Table', level: 1 })).toBeVisible();

        const tabs = adminPage.getByRole('navigation', { name: 'Tabs' });
        for (const level of ['All Logs', 'Error', 'Warning', 'Info', 'Debug']) {
            await expect(tabs.getByRole('button', { name: level })).toBeVisible();
        }

        await tabs.getByRole('button', { name: 'Error' }).click();
        await expect(adminPage.getByRole('table', { name: 'logs' })).toBeVisible();
        await expect(adminPage.getByRole('button', { name: 'Clear Logs' })).toBeVisible();
    });

    // BUG (achyutn/filament-log-viewer): its parser reads the entries as `[date] env.LEVEL: message` with `\w+` for the
    // environment, so that no entry of an environment with a hyphen (`e2e-app`, `e2e-setup` here, but also e.g.
    // `pr-123` previews) is ever listed: the viewer shows "No logs" while `laravel.log` has thousands of them.
    test.fixme('the log viewer lists the entries of the log', async ({ adminPage }) => {
        await visit(adminPage, '/app/logs');

        await expect(adminPage.getByRole('table', { name: 'logs' }).locator('.fi-ta-row').first()).toBeVisible();
    });
});

test.describe('Mail Logs', () => {
    test('list a mail sent by resending an invitation, and show its body', async ({ adminPage }) => {
        const user = createInvitedUser();

        await visit(adminPage, `/app/users/${user.id}`);
        await adminPage.locator('.fi-header').getByRole('button', { name: 'Resend invitation' }).click();
        await expect(modal(adminPage)).toContainText(user.email);
        await confirmModal(adminPage, 'Resend invitation');
        await expectNotification(adminPage, `An invitation to choose the password has been sent to ${user.email}.`);

        await visit(adminPage, '/app/mail-logs');
        await searchTable(adminPage, user.email);

        const mail = row(adminPage, user.email);
        await expect(mail).toContainText('Your OCH E2E account');
        await expect(adminPage.locator('.fi-ta-row')).toHaveCount(1);

        await rowAction(adminPage, user.email, 'View');
        const details = modal(adminPage);
        await expect(details.getByRole('heading', { name: 'View Mail Log' })).toBeVisible();
        await expect(details).toContainText(user.email);
        await expect(details).toContainText('OCH E2E <e2e@example.com>');

        const body = details.frameLocator('iframe').first();
        await expect(body.getByText('An account has been created for you on OCH E2E.')).toBeVisible();
        await expect(body.getByRole('link', { name: 'Choose password' }).first()).toHaveAttribute('href', /\/app\/password-reset\/reset\?/);
    });
});

test.describe('Activity Logs', () => {
    test('show the creation of a zone by the administrator, with its changes', async ({ adminPage }) => {
        const zoneName = uniqueName('Logged Zone');

        await visit(adminPage, '/app/territory-zones/create');
        await field(adminPage, 'Name').fill(zoneName);
        await adminPage.getByRole('button', { name: 'Create', exact: true }).click();
        await expectNotification(adminPage, 'Created');

        const { zoneId, activityId } = php<{ zoneId: number; activityId: number }>(`
            $zone = \\App\\Models\\TerritoryZone::where('name', ${JSON.stringify(zoneName)})->firstOrFail();

            return [
                'zoneId' => $zone->id,
                'activityId' => \\Spatie\\Activitylog\\Models\\Activity::query()
                    ->where('subject_type', $zone->getMorphClass())
                    ->where('subject_id', $zone->id)
                    ->where('event', 'created')
                    ->value('id'),
            ];
        `);
        expect(activityId).toBeTruthy();

        await visit(adminPage, '/app/activitylogs');
        // The changes are searched too
        await searchTable(adminPage, zoneName);
        const activity = adminPage.locator('.fi-ta-row').filter({ has: adminPage.locator(`a[href$="/app/activitylogs/${activityId}"]`) });
        await expect(activity).toContainText(`Territory Zone # ${zoneId}`);
        await expect(activity).toContainText('Created');
        await expect(activity).toContainText(users.admin.name);

        await activity.getByRole('link').first().click();
        await expect(adminPage.getByRole('heading', { name: 'View Activity Log', level: 1 })).toBeVisible();
        await expect(field(adminPage, 'Subject')).toHaveValue(`Territory Zone # ${zoneId}`);
        await expect(field(adminPage, 'Description')).toHaveValue('created');
        await expect(adminPage.getByRole('region', { name: 'Changes' }).getByRole('textbox', { name: 'Value' }).first()).toHaveValue(zoneName);
    });
});

test.describe('Developer pages for the other users', () => {
    // Only the super administrators see the logs (the `view-logs` gate), even the service overseer who has the
    // Activity permissions
    for (const role of ['servant', 'overseer', 'publisher'] as Role[]) {
        test(`${role} has no Developer pages and is forbidden them`, async ({ browser }) => {
            const page = await pageAs(browser, role);
            await visit(page, '/app');

            const sidebar = page.getByRole('navigation', { name: 'Sidebar navigation' });
            await expect(sidebar.getByRole('link', { name: 'Dashboard' })).toBeVisible();
            for (const item of ['Mail Logs', 'Activity Logs', 'Log Viewer']) {
                await expect(sidebar.getByRole('link', { name: item })).toHaveCount(0);
            }

            for (const url of ['/app/logs', '/app/mail-logs', '/app/activitylogs', '/app/activitylogs/1']) {
                const response = await page.context().request.get(url, { maxRedirects: 0 });
                expect(response.status(), url).toBe(403);
            }

            await page.context().close();
        });
    }
});
