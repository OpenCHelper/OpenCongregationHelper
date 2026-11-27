import { test as base, expect, type Browser, type Page } from '@playwright/test';
import { appURL } from '../../../playwright.config';
import { field } from './filament';
import { type Role, storageStateOf, users } from './users';

let counter = 0;

/**
 * A name nobody else uses, so that the tests running in parallel on the same database never see each other's
 * records.
 */
export const uniqueName = (prefix: string): string =>
    `${prefix} ${Date.now().toString(36)}${(counter++).toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`;

/**
 * Open a page signed in as a user of a role (in a browser context of its own).
 */
export async function pageAs(browser: Browser, role: Role): Promise<Page> {
    const context = await browser.newContext({ baseURL: appURL, storageState: storageStateOf(role) });

    return context.newPage();
}

/**
 * Make the requests of the page come from an address of their own (the application trusts the forwarded headers of
 * the tests, see `serve.sh`): the sign-ins are rate limited by address, and every test signs in from 127.0.0.1.
 */
export async function useOwnAddress(page: Page, address?: string): Promise<string> {
    const ip = address ?? `10.${Math.floor(Math.random() * 256)}.${Math.floor(Math.random() * 256)}.${1 + Math.floor(Math.random() * 254)}`;
    await page.context().setExtraHTTPHeaders({ 'X-Forwarded-For': ip });

    return ip;
}

/**
 * Sign in through the login form.
 */
export async function logIn(page: Page, email: string, password: string): Promise<void> {
    await useOwnAddress(page);
    await page.goto('/app/login');
    await page.waitForLoadState('networkidle');
    await field(page, 'Email address').fill(email);
    await field(page, 'Password').fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();
}

type Fixtures = {
    /** The page of a signed-in super administrator (not the root user). */
    adminPage: Page;
    /** The page of the territory servant. */
    servantPage: Page;
    /** The page of a publisher. */
    publisherPage: Page;
};

export const test = base.extend<Fixtures>({
    adminPage: async ({ browser }, use) => {
        const page = await pageAs(browser, 'admin');
        await use(page);
        await page.context().close();
    },
    servantPage: async ({ browser }, use) => {
        const page = await pageAs(browser, 'servant');
        await use(page);
        await page.context().close();
    },
    publisherPage: async ({ browser }, use) => {
        const page = await pageAs(browser, 'publisher');
        await use(page);
        await page.context().close();
    },
});

export { expect, users };
