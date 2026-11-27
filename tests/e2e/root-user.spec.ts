import { php } from './support/artisan';
import { expect, pageAs, test, users } from './support/fixtures';
import { visit } from './support/filament';

/**
 * The root user (the first one, created by `och:install`), whose account is protected outside production, so that the
 * demos and the previews always keep a way in. Nothing is changed here: the handover of the role is possible only in
 * production (see `RootUserTransferTest`).
 */
const adminId = (): number => php<number>(`return \\App\\Models\\User::where('email', '${users.admin.email}')->value('id');`);

test.describe('root user', () => {
    test('the other administrators cannot edit the root user', async ({ adminPage }) => {
        await visit(adminPage, '/app/users/1');
        await expect(adminPage.getByRole('heading', { name: `View ${users.root.name}` })).toBeVisible();

        // The edit button is there, disabled, and the edit page is refused
        await expect(adminPage.getByRole('link', { name: 'Edit' })).toHaveCount(0);
        await expect(adminPage.locator('.fi-header').getByText('Edit')).toBeVisible();
        await expect(adminPage.getByRole('button', { name: 'Impersonate' })).toHaveCount(0);
        await expect(adminPage.getByRole('button', { name: 'Delete' })).toHaveCount(0);

        const response = await adminPage.goto('/app/users/1/edit');
        expect(response?.status()).toBe(403);
    });

    test('the root user can edit itself, but not its protected fields', async ({ browser }) => {
        const root = await pageAs(browser, 'root');

        await visit(root, '/app/users/1');
        await root.locator('.fi-header').getByRole('link', { name: 'Edit' }).click();
        await expect(root).toHaveURL(/\/app\/users\/1\/edit$/);

        await visit(root, '/app/edit-profile');
        await expect(root.getByText('Password changes are not allowed for the root user in non-production environments.')).toBeVisible();
        await expect(root.getByText('The root user cannot be deleted under any circumstances.')).toBeVisible();

        await root.context().close();
    });

    test('the role of root user can be handed over only in production', async ({ browser }) => {
        const root = await pageAs(browser, 'root');

        await visit(root, `/app/users/${adminId()}`);
        const handOver = root.getByRole('button', { name: 'Make root user' });
        await expect(handOver).toBeDisabled();

        await handOver.hover({ force: true });
        await expect(root.getByText('The root user can be changed only in production: on demos and previews it stays the first user.')).toBeVisible();

        await root.context().close();
    });

    test('only the root user is offered to hand the role over', async ({ adminPage }) => {
        await visit(adminPage, `/app/users/${php<number>(`return \\App\\Models\\User::where('email', '${users.servant.email}')->value('id');`)}`);

        await expect(adminPage.getByRole('heading', { name: `View ${users.servant.name}` })).toBeVisible();
        await expect(adminPage.getByRole('button', { name: 'Make root user' })).toHaveCount(0);
    });
});
