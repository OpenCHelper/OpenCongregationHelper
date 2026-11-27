import type { Locator, Page } from '@playwright/test';
import { test, expect, uniqueName } from './support/fixtures';
import { php } from './support/artisan';
import { confirmModal, expectNotification, labelled, modal, row, rowAction, visit } from './support/filament';

/**
 * A throwaway user of the congregation, to be made a member of a department.
 */
function createUser(): { id: number; firstName: string; name: string } {
    const firstName = uniqueName('Member');
    const id = php<number>(`
        return \\App\\Models\\User::factory()->create([
            'first_name' => ${JSON.stringify(firstName)},
            'last_name' => 'Department',
            'locale' => 'en',
        ])->assignRole('publisher')->id;
    `);

    return { id, firstName, name: `${firstName} Department` };
}

/**
 * The id of a department, by its key.
 */
const departmentId = (key: string): number => php<number>(`return \\App\\Models\\Department::where('key', ${JSON.stringify(key)})->value('id');`);

/**
 * The role of a user in a department, `null` when they are not a member.
 */
const roleIn = (departmentKey: string, userId: number): string | null => php<string | null>(`
    $member = \\App\\Models\\Department::where('key', ${JSON.stringify(departmentKey)})->first()->members()->whereKey(${userId})->first();
    $role = $member?->pivot->role;

    return $role instanceof \\BackedEnum ? $role->value : $role;
`);

/**
 * Add members to a department: pick them in the "Brothers" select of the modal, by name.
 */
async function addMembers(page: Page, users: { firstName: string }[], role: 'Overseer' | 'Assistant'): Promise<void> {
    await page.getByRole('button', { name: 'Add members' }).click();
    const dialog = modal(page);
    await expect(dialog.getByRole('heading', { name: /^Add members to / })).toBeVisible();

    for (const user of users) {
        await pickOption(page, dialog, 'Brothers', user.firstName);
        await expect(dialog.getByRole('button', { name: `Remove ${user.firstName} Department` })).toBeVisible();
    }

    await chooseRole(dialog, role);
    await confirmModal(page, 'Add');
}

/**
 * Choose the role in the department, in the modal.
 */
async function chooseRole(dialog: Locator, role: 'Overseer' | 'Assistant'): Promise<void> {
    await dialog.getByRole('radiogroup', { name: labelled('Role in the department') }).getByText(role, { exact: true }).click();
    await expect(dialog.getByRole('radio', { name: role })).toBeChecked();
}

/**
 * Pick an option of a searchable select of Filament by searching it (the users are searched by first or last name).
 */
async function pickOption(page: Page, scope: Locator, label: string, option: string): Promise<void> {
    const search = page.locator('.fi-select-input-search-ctn input').filter({ visible: true }).first();

    // The select is a button (named after its label, then after the options chosen); a multiple one stays open
    if (!(await search.isVisible())) {
        await scope.locator('.fi-fo-field').filter({ has: page.locator('label', { hasText: labelled(label) }) }).locator('.fi-select-input-btn').click();
    }

    await search.fill(option);
    await page.getByRole('option', { name: option }).filter({ visible: true }).first().click();
}

test.describe('Departments', () => {
    test('list the departments with their sections and overseers', async ({ adminPage }) => {
        await visit(adminPage, '/app/departments');

        await expect(adminPage.getByRole('heading', { name: 'Departments', level: 1 })).toBeVisible();
        await expect(adminPage.getByText('The members of a department manage its sections.')).toBeVisible();

        const territories = row(adminPage, 'Territory Department');
        await expect(territories).toContainText('Territories, zones, assignments and campaigns.');
        await expect(territories).toContainText('Sam Servant');
        for (const section of ['Territories', 'Zones', 'Assignments', 'Campaigns']) {
            await expect(territories.getByRole('listitem').filter({ hasText: section })).toBeVisible();
        }

        await expect(row(adminPage, 'Public Witnessing')).toContainText('Posters');
        await expect(row(adminPage, 'Publications')).toContainText('Publication list');
        // Departments are defined by the application
        await expect(adminPage.locator('.fi-header').getByRole('link', { name: /new/i })).toHaveCount(0);
    });

    test('view a department with its sections, overseers and members', async ({ adminPage }) => {
        await visit(adminPage, '/app/departments');
        await row(adminPage, 'Territory Department').getByRole('link').first().click();

        await expect(adminPage).toHaveURL(new RegExp(`/app/departments/${departmentId('territories')}$`));
        await expect(adminPage.getByRole('heading', { name: 'Territory Department', level: 1 })).toBeVisible();
        await expect(adminPage.getByRole('definition').filter({ hasText: 'Sam Servant' })).toBeVisible();

        const servant = row(adminPage, 'servant@e2e.test');
        await expect(servant).toContainText('Overseer');
        await expect(servant.getByRole('button', { name: 'Change role' })).toBeVisible();
    });
});

test.describe('Members of a department', () => {
    test('the administrator adds a member as overseer, changes their role and removes them', async ({ adminPage }) => {
        const user = createUser();

        await visit(adminPage, `/app/departments/${departmentId('publications')}`);
        await addMembers(adminPage, [user], 'Overseer');
        await expectNotification(adminPage, 'Members added');

        await expect(row(adminPage, user.name)).toContainText('Overseer');
        expect(roleIn('publications', user.id)).toBe('overseer');

        await rowAction(adminPage, user.name, 'Change role');
        await expect(modal(adminPage).getByRole('heading', { name: user.name })).toBeVisible();
        await chooseRole(modal(adminPage), 'Assistant');
        await confirmModal(adminPage, 'Save changes');

        await expect(row(adminPage, user.name)).toContainText('Assistant');
        expect(roleIn('publications', user.id)).toBe('assistant');

        await rowAction(adminPage, user.name, 'Remove');
        await expect(modal(adminPage).getByRole('heading', { name: `Remove ${user.name} from the department` })).toBeVisible();
        await confirmModal(adminPage, 'Remove');
        await expectNotification(adminPage, 'Member removed');

        await expect(adminPage.locator('.fi-ta-row').filter({ hasText: user.name })).toHaveCount(0);
        expect(roleIn('publications', user.id)).toBeNull();
    });

    test('the administrator adds several members at once and removes them in bulk', async ({ adminPage }) => {
        const first = createUser();
        const second = createUser();

        await visit(adminPage, `/app/departments/${departmentId('public_witnessing')}`);
        await addMembers(adminPage, [first, second], 'Assistant');
        await expectNotification(adminPage, 'Members added');

        await expect(row(adminPage, first.name)).toContainText('Assistant');
        await expect(row(adminPage, second.name)).toContainText('Assistant');

        // `bulkAction()` looks for a button, the actions of the group are menu items
        for (const user of [first, second]) {
            await row(adminPage, user.name).getByRole('checkbox').check();
        }
        await adminPage.getByRole('button', { name: 'Bulk actions' }).click();
        await adminPage.getByRole('menu', { name: 'Bulk actions' }).getByRole('menuitem', { name: 'Remove' }).click();
        await expect(modal(adminPage).getByRole('heading', { name: 'Detach selected Members' })).toBeVisible();
        await confirmModal(adminPage, 'Detach');

        await expect(adminPage.locator('.fi-ta-row').filter({ hasText: first.name })).toHaveCount(0);
        await expect(adminPage.locator('.fi-ta-row').filter({ hasText: second.name })).toHaveCount(0);
        expect(roleIn('public_witnessing', first.id)).toBeNull();
        expect(roleIn('public_witnessing', second.id)).toBeNull();
    });

    test('an overseer adds assistants to their department, but neither appoints nor removes the overseers', async ({ servantPage }) => {
        const user = createUser();

        await visit(servantPage, `/app/departments/${departmentId('territories')}`);

        // The servant cannot change their own role of overseer
        const overseer = row(servantPage, 'servant@e2e.test');
        await expect(overseer).toContainText('Overseer');
        await expect(overseer.getByRole('button', { name: 'Change role' })).toHaveCount(0);
        await expect(overseer.getByRole('button', { name: 'Remove' })).toHaveCount(0);

        await servantPage.getByRole('button', { name: 'Add members' }).click();
        const dialog = modal(servantPage);
        await pickOption(servantPage, dialog, 'Brothers', user.firstName);
        await expect(dialog.getByRole('radio', { name: 'Overseer' })).toBeDisabled();
        await chooseRole(dialog, 'Assistant');
        await confirmModal(servantPage, 'Add');
        await expectNotification(servantPage, 'Members added');

        await expect(row(servantPage, user.name)).toContainText('Assistant');
        expect(roleIn('territories', user.id)).toBe('assistant');

        // They manage the assistants
        await rowAction(servantPage, user.name, 'Remove');
        await confirmModal(servantPage, 'Remove');
        await expectNotification(servantPage, 'Member removed');
        expect(roleIn('territories', user.id)).toBeNull();
    });
});
