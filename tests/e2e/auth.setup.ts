import { test as setup, expect } from '@playwright/test';
import { logIn } from './support/fixtures';
import { roles, storageStateOf, users } from './support/users';

/**
 * Sign in once with every user, saving the browser state that the tests reuse (see `pageAs()`).
 */
for (const role of roles) {
    setup(`sign in as ${role}`, async ({ page }) => {
        await logIn(page, users[role].email, users[role].password);
        await expect(page).not.toHaveURL(/\/login/);
        await page.context().storageState({ path: storageStateOf(role) });
    });
}
