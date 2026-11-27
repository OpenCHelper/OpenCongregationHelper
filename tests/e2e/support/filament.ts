import { expect, type Locator, type Page } from '@playwright/test';

const escapeRegExp = (text: string): string => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * The accessible name of a Filament field: its label, followed by `*` when it is required.
 */
export const labelled = (label: string): RegExp => new RegExp(`^\\s*${escapeRegExp(label)}\\s*\\*?\\s*$`);

/**
 * A field of a form, found by its exact label.
 */
export const field = (scope: Page | Locator, label: string): Locator => scope.getByLabel(labelled(label));

/**
 * Wait until Livewire has handled the requests in progress (e.g. after typing in a live field).
 */
export async function settle(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle');
}

/**
 * Choose an option of a Filament select, either native or searchable (Choices.js style).
 */
export async function choose(scope: Page | Locator, label: string, option: string): Promise<void> {
    const page = 'page' in scope ? scope.page() : scope;
    const select = field(scope, label);

    if ((await select.evaluate((element) => element.tagName)) === 'SELECT') {
        await select.selectOption({ label: option });

        return;
    }

    await select.click();

    const search = page.locator('.fi-select-input-search-ctn input, .choices__input--cloned').filter({ visible: true }).first();

    if (await search.count()) {
        await search.fill(option);
    }

    await page.getByRole('option', { name: option, exact: false }).filter({ visible: true }).first().click();
}

/**
 * The modal currently open.
 */
export const modal = (page: Page): Locator => page.locator('.fi-modal-window').filter({ visible: true }).last();

/**
 * The row of the table that contains the text.
 */
export const row = (page: Page, text: string): Locator =>
    page.locator('.fi-ta-row').filter({ hasText: text }).first();

/**
 * Search a Filament table.
 */
export async function searchTable(page: Page, text: string): Promise<void> {
    const search = page.getByRole('searchbox', { name: /search/i }).filter({ visible: true }).last();
    await search.fill(text);
    await settle(page);
}

/**
 * Click an action of a table row: a visible button or link, or one hidden in the row's action group.
 */
export async function rowAction(page: Page, rowText: string, action: string | RegExp): Promise<void> {
    const tableRow = row(page, rowText);
    const name = typeof action === 'string' ? new RegExp(`^\\s*${escapeRegExp(action)}\\s*$`, 'i') : action;
    const direct = tableRow.getByRole('button', { name }).or(tableRow.getByRole('link', { name })).first();

    if (await direct.isVisible()) {
        await direct.click();

        return;
    }

    await tableRow.getByRole('button', { name: /actions/i }).last().click();
    await page.locator('.fi-dropdown-panel').filter({ visible: true }).getByRole('button', { name }).or(
        page.locator('.fi-dropdown-panel').filter({ visible: true }).getByRole('link', { name }),
    ).first().click();
}

/**
 * Click a header action of the page, opening the action group of the header when it is hidden there.
 */
export async function headerAction(page: Page, action: string | RegExp): Promise<void> {
    const name = typeof action === 'string' ? new RegExp(`^\\s*${escapeRegExp(action)}\\s*$`, 'i') : action;
    const header = page.locator('.fi-header');
    const direct = header.getByRole('button', { name }).or(header.getByRole('link', { name })).first();

    if (await direct.isVisible()) {
        await direct.click();

        return;
    }

    await header.getByRole('button', { name: /actions/i }).last().click();
    const panel = page.locator('.fi-dropdown-panel').filter({ visible: true });
    await panel.getByRole('button', { name }).or(panel.getByRole('link', { name })).first().click();
}

/**
 * Select the rows of a table, then run one of its bulk actions.
 */
export async function bulkAction(page: Page, rowTexts: string[], action: string | RegExp): Promise<void> {
    for (const text of rowTexts) {
        await row(page, text).getByRole('checkbox').first().check();
    }

    const name = typeof action === 'string' ? new RegExp(`^\\s*${escapeRegExp(action)}\\s*$`, 'i') : action;
    const toolbar = page.locator('.fi-ta-header-toolbar, .fi-ta-selection-indicator');
    const direct = toolbar.getByRole('button', { name }).first();

    if (await direct.isVisible()) {
        await direct.click();

        return;
    }

    await page.getByRole('button', { name: /bulk actions/i }).filter({ visible: true }).first().click();
    await page.locator('.fi-dropdown-panel').filter({ visible: true }).getByRole('button', { name }).first().click();
}

/**
 * Submit the open modal with its primary (or the named) button.
 */
export async function confirmModal(page: Page, button: string | RegExp = /confirm|submit|save|create|delete|yes|send/i): Promise<void> {
    const name = typeof button === 'string' ? new RegExp(`^\\s*${escapeRegExp(button)}\\s*$`, 'i') : button;
    await modal(page).locator('.fi-modal-footer').getByRole('button', { name }).first().click();
}

/**
 * Expect a notification (toast) with the text.
 */
export async function expectNotification(page: Page, text: string | RegExp): Promise<void> {
    await expect(page.locator('.fi-no-notification').filter({ hasText: text }).first()).toBeVisible();
}

/**
 * Open a page and wait for Livewire to load it.
 */
export async function visit(page: Page, url: string): Promise<void> {
    await page.goto(url);
    await settle(page);
}
