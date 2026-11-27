import type { Locator, Page } from '@playwright/test';
import { test, expect, pageAs } from './support/fixtures';
import { php } from './support/artisan';
import { field, modal, visit } from './support/filament';
import type { Role } from './support/users';

/**
 * The items of the sidebar, with the page each one opens (the Settings cluster by its first page, the cluster itself
 * only redirects to it).
 */
const navigation = {
    Dashboard: '/app',
    Territories: '/app/territories',
    Zones: '/app/territory-zones',
    Campaigns: '/app/campaigns',
    Assignments: '/app/territory-assignments',
    Posters: '/app/posters',
    Storage: '/app/storage-locations',
    'Regular publications': '/app/publication-subscribers',
    Requests: '/app/publication-requests',
    'Publication list': '/app/publications',
    Users: '/app/users',
    Departments: '/app/departments',
    Roles: '/app/shield/roles',
    Tags: '/app/tags',
    Settings: '/app/settings/general',
    'Mail Logs': '/app/mail-logs',
    'Activity Logs': '/app/activitylogs',
    'Log Viewer': '/app/logs',
} as const;

type NavigationItem = keyof typeof navigation;

const territoryDepartment: NavigationItem[] = ['Territories', 'Zones', 'Campaigns', 'Assignments'];

/**
 * What each seeded role reaches (see `FakeRoleSeeder` and the policies): `super_admin` everything; the territory servant
 * the territory department (and Departments, as the overseer of the territories department, see `DepartmentPolicy`),
 * the users and the tags; the service overseer reads the territories, users and tags; the group overseer reads the
 * territories and the users; the publisher reads the territories, zones and campaigns; a user without a role only the
 * dashboard. The logs are for the super administrators only (the `view-logs` gate), even with the Activity permission
 * of the service overseer.
 */
const visibleNavigation: Record<Exclude<Role, 'root'>, NavigationItem[]> = {
    admin: Object.keys(navigation) as NavigationItem[],
    servant: ['Dashboard', ...territoryDepartment, 'Users', 'Departments', 'Tags'],
    overseer: ['Dashboard', ...territoryDepartment, 'Users', 'Tags'],
    group: ['Dashboard', ...territoryDepartment, 'Users'],
    publisher: ['Dashboard', 'Territories', 'Zones', 'Campaigns'],
    norole: ['Dashboard'],
};

const escapeRegExp = (text: string): string => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * A link of the sidebar, whatever its badge (e.g. "Campaigns 1").
 */
const sidebarLink = (page: Page, item: NavigationItem): Locator =>
    page.getByRole('navigation', { name: 'Sidebar navigation' }).getByRole('link', { name: new RegExp(`^${escapeRegExp(item)}(\\s+\\d+)?$`) });

/**
 * A button or link of the header of the page.
 */
const headerControl = (page: Page, name: string): Locator => {
    const header = page.locator('.fi-header');
    const exact = new RegExp(`^\\s*${escapeRegExp(name)}\\s*$`);

    return header.getByRole('link', { name: exact }).or(header.getByRole('button', { name: exact }));
};

/**
 * The HTTP status of a page for the user of the page (without following the redirects).
 */
async function statusOf(page: Page, url: string): Promise<number> {
    return (await page.context().request.get(url, { maxRedirects: 0 })).status();
}

test.describe('Navigation and pages by role', () => {
    for (const [role, visible] of Object.entries(visibleNavigation) as [Exclude<Role, 'root'>, NavigationItem[]][]) {
        test(`${role} sees only the sidebar items of their permissions`, async ({ browser }) => {
            const page = await pageAs(browser, role);
            await visit(page, '/app');

            for (const item of Object.keys(navigation) as NavigationItem[]) {
                if (visible.includes(item)) {
                    await expect(sidebarLink(page, item), `${item} for ${role}`).toBeVisible();
                } else {
                    await expect(sidebarLink(page, item), `${item} for ${role}`).toHaveCount(0);
                }
            }

            await page.context().close();
        });

        test(`${role} opens the pages of their items and is forbidden the others`, async ({ browser }) => {
            const page = await pageAs(browser, role);

            for (const [item, url] of Object.entries(navigation) as [NavigationItem, string][]) {
                expect(await statusOf(page, url), `${url} for ${role}`).toBe(visible.includes(item) ? 200 : 403);
            }

            await page.context().close();
        });
    }

    test('a publisher is shown the forbidden page for the administration pages', async ({ publisherPage }) => {
        for (const url of ['/app/users', '/app/settings/general', '/app/logs', '/app/shield/roles']) {
            const response = await publisherPage.goto(url);

            expect(response?.status(), url).toBe(403);
            await expect(publisherPage.getByText(/forbidden/i).first()).toBeVisible();
        }
    });

    test('a publisher navigates the territories from the menu on a phone @mobile', async ({ publisherPage, isMobile }) => {
        test.skip(!isMobile, 'The sidebar of the desktop is checked above.');

        await visit(publisherPage, '/app');

        // On a phone the sidebar is closed: the bottom navigation has the first items, and opens it with "More"
        const bottomNavigation = publisherPage.getByRole('navigation', { name: 'Bottom navigation' });
        await expect(bottomNavigation.getByRole('link', { name: 'Territories' })).toBeVisible();
        await bottomNavigation.getByRole('button', { name: 'More' }).click();

        await expect(sidebarLink(publisherPage, 'Zones')).toBeInViewport();
        await expect(sidebarLink(publisherPage, 'Users')).toHaveCount(0);
        await expect(sidebarLink(publisherPage, 'Assignments')).toHaveCount(0);
        await sidebarLink(publisherPage, 'Territories').click();

        await expect(publisherPage).toHaveURL(/\/app\/territories$/);
        await expect(publisherPage.getByRole('heading', { name: 'Territories', level: 1 })).toBeVisible();
        await expect(headerControl(publisherPage, 'New territory')).toHaveCount(0);
    });
});

test.describe('Actions by role', () => {
    let territoryId: number;
    let zoneId: number;
    let campaignId: number;

    test.beforeAll(() => {
        ({ territoryId, zoneId, campaignId } = php<{ territoryId: number; zoneId: number; campaignId: number }>(`
            return [
                'territoryId' => \\App\\Models\\Territory::where('name', 'E2E Free')->value('id'),
                'zoneId' => \\App\\Models\\TerritoryZone::where('name', 'E2E Zone')->value('id'),
                'campaignId' => \\App\\Models\\Campaign::where('name', 'E2E Expired Campaign')->value('id'),
            ];
        `));
    });

    /**
     * The create actions of the lists, by role: the publisher only reads, the service overseer organizes the
     * campaigns, the group overseer assigns the territories.
     */
    const createActions: { url: string; action: string; roles: Role[] }[] = [
        { url: '/app/territories', action: 'New territory', roles: ['admin', 'servant'] },
        { url: '/app/territories', action: 'Import territories', roles: ['admin', 'servant'] },
        { url: '/app/territory-zones', action: 'New zone', roles: ['admin', 'servant'] },
        { url: '/app/campaigns', action: 'New campaign', roles: ['admin', 'servant', 'overseer'] },
        { url: '/app/territory-assignments', action: 'New assignment', roles: ['admin', 'servant', 'group'] },
        { url: '/app/users', action: 'New user', roles: ['admin'] },
    ];

    for (const role of ['admin', 'servant', 'overseer', 'group', 'publisher'] as const) {
        test(`${role} is offered only the create actions of their permissions`, async ({ browser }) => {
            const page = await pageAs(browser, role);

            for (const { url, action, roles } of createActions) {
                if ((await statusOf(page, url)) !== 200) {
                    continue;
                }

                await visit(page, url);

                if (roles.includes(role)) {
                    await expect(headerControl(page, action), `${action} for ${role}`).toBeVisible();
                } else {
                    await expect(headerControl(page, action), `${action} for ${role}`).toHaveCount(0);
                }
            }

            await page.context().close();
        });
    }

    test('a publisher reads the territories, zones and campaigns without edit or delete actions', async ({ publisherPage }) => {
        await visit(publisherPage, `/app/territories/${territoryId}`);
        await expect(publisherPage.getByRole('heading', { name: 'E2E Free', level: 1 })).toBeVisible();
        await expect(headerControl(publisherPage, 'Assign Territory')).toHaveCount(0);
        await expect(headerControl(publisherPage, 'Edit')).toHaveCount(0);

        await visit(publisherPage, `/app/territory-zones/${zoneId}`);
        await expect(publisherPage.getByRole('heading', { name: 'E2E Zone', level: 1 })).toBeVisible();
        await expect(headerControl(publisherPage, 'Edit')).toHaveCount(0);
        await expect(headerControl(publisherPage, 'Delete')).toHaveCount(0);

        await visit(publisherPage, '/app/campaigns');
        await expect(publisherPage.locator('.fi-ta-row').first()).toBeVisible();
        await expect(publisherPage.locator('.fi-ta-row').getByRole('button', { name: /^(Edit|Delete)$/ })).toHaveCount(0);
        await expect(publisherPage.locator('.fi-ta-row').getByRole('link', { name: /^Edit$/ })).toHaveCount(0);

        for (const url of [`/app/territories/${territoryId}/edit`, `/app/territory-zones/${zoneId}/edit`, '/app/territories/create']) {
            expect(await statusOf(publisherPage, url), url).toBe(403);
        }
    });

    test('the service overseer edits the campaigns but only reads the zones', async ({ browser }) => {
        const page = await pageAs(browser, 'overseer');

        await visit(page, `/app/campaigns/${campaignId}`);
        await headerControl(page, 'Edit').click();
        // The campaigns are edited in a modal
        await expect(field(modal(page), 'Name')).toHaveValue('E2E Expired Campaign');
        await modal(page).getByRole('button', { name: 'Cancel' }).click();

        await visit(page, `/app/territory-zones/${zoneId}`);
        await expect(page.getByRole('heading', { name: 'E2E Zone', level: 1 })).toBeVisible();
        await expect(headerControl(page, 'Edit')).toHaveCount(0);
        await expect(headerControl(page, 'Delete')).toHaveCount(0);

        await page.context().close();
    });

    test('the territory servant edits and assigns the territories', async ({ servantPage }) => {
        await visit(servantPage, `/app/territories/${territoryId}`);
        await expect(headerControl(servantPage, 'Assign Territory')).toBeVisible();

        await visit(servantPage, `/app/territory-zones/${zoneId}`);
        await expect(headerControl(servantPage, 'Edit')).toBeVisible();
        await expect(headerControl(servantPage, 'Delete')).toBeVisible();

        expect(await statusOf(servantPage, `/app/territories/${territoryId}/edit`)).toBe(200);
        // Users are read only for them
        expect(await statusOf(servantPage, '/app/users/create')).toBe(403);
    });
});
