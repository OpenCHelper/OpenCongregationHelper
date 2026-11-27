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

/**
 * An item of the dropdown menu open on the page (an action group, the bulk actions): Filament renders them as menu
 * items, not as buttons.
 */
export const menuItem = (page: Page, name: string | RegExp): Locator => {
    const pattern = typeof name === 'string' ? new RegExp(`^\\s*${escapeRegExp(name)}\\s*$`, 'i') : name;
    const panel = page.locator('.fi-dropdown-panel').filter({ visible: true });

    return panel.getByRole('menuitem', { name: pattern })
        .or(panel.getByRole('button', { name: pattern }))
        .or(panel.getByRole('link', { name: pattern }))
        .first();
};

/**
 * Click an action of the action group ("Actions" menu) of a table row.
 */
export async function rowMenuAction(page: Page, rowText: string, action: string | RegExp): Promise<void> {
    await row(page, rowText).getByRole('button', { name: /^\s*actions\s*$/i }).last().click();
    await menuItem(page, action).click();
}

/**
 * Click an action of a named action group of the page header (e.g. "Import / Export").
 */
export async function headerMenuAction(page: Page, group: string | RegExp, action: string | RegExp): Promise<void> {
    const name = typeof group === 'string' ? new RegExp(`^\\s*${escapeRegExp(group)}\\s*$`, 'i') : group;
    await page.locator('.fi-header').getByRole('button', { name }).first().click();
    await menuItem(page, action).click();
}

/**
 * Select the rows of a table, then run one of the actions of its "Bulk actions" menu.
 */
export async function bulkMenuAction(page: Page, rowTexts: string[], action: string | RegExp): Promise<void> {
    for (const text of rowTexts) {
        await row(page, text).getByRole('checkbox').first().check();
    }

    await page.getByRole('button', { name: /^\s*bulk actions\s*$/i }).filter({ visible: true }).first().click();
    await menuItem(page, action).click();
}

/**
 * Choose an option of a Filament select by its exact label: a native select, or a searchable one, whose label also
 * names its list of options (which makes `choose()` find two elements). The options are searched first, since a
 * preloaded list may not hold them all: by the whole label, else by its first word.
 */
export async function chooseOption(scope: Page | Locator, label: string, option: string): Promise<void> {
    const page = 'page' in scope ? scope.page() : scope;
    const trigger = scope.getByRole('combobox', { name: labelled(label) })
        .or(scope.getByRole('button', { name: labelled(label) }))
        .first();

    if ((await trigger.evaluate((element) => element.tagName)) === 'SELECT') {
        await trigger.selectOption({ label: option });

        return;
    }

    await trigger.click();

    const search = page.locator('.fi-select-input-search-ctn input').filter({ visible: true }).first();
    const choice = page.getByRole('listbox').filter({ visible: true }).getByRole('option', { name: option, exact: true }).first();

    if (await search.count()) {
        await search.fill(option);

        // A select searching each column apart (e.g. first and last name) finds nothing for the whole label
        const found = await choice.waitFor({ state: 'visible', timeout: 5_000 }).then(() => true, () => false);

        if (!found) {
            await search.fill(option.split(/\s+/)[0]);
        }
    }

    await choice.click();
}

/**
 * Run an export from the "Import / Export" menu of the page header (e.g. "Export Posters") with the default options,
 * then download the CSV file linked by the notification (the queue is synchronous): its name and its rows (without the
 * byte order mark).
 */
export async function downloadExport(page: Page, action: string): Promise<{ fileName: string; lines: string[] }> {
    await headerMenuAction(page, 'Import / Export', action);
    await expect(modal(page).getByRole('heading', { name: action })).toBeVisible();
    await confirmModal(page, 'Export');

    const link = page.locator('.fi-no-notification').getByRole('link', { name: /\.csv/ }).first();
    const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
    const { readFile } = await import('node:fs/promises');
    const csv = (await readFile(await download.path(), 'utf8')).replace(/^﻿/, '');

    return { fileName: download.suggestedFilename(), lines: csv.trim().split(/\r?\n/) };
}

/**
 * Run an import from the "Import / Export" menu of the page header (e.g. "Import Posters") with a CSV file, its columns
 * matched by their headers. The queue is synchronous: the rows are imported once this returns, or soon after.
 */
export async function uploadImport(page: Page, action: string, file: string): Promise<void> {
    await headerMenuAction(page, 'Import / Export', action);
    await expect(modal(page).getByRole('heading', { name: action })).toBeVisible();
    await expect(modal(page).getByText('Upload a CSV file')).toBeVisible();
    await modal(page).locator('input[type=file]').setInputFiles(file);

    // Enabled once the file is uploaded and its columns are matched
    const button = modal(page).locator('.fi-modal-footer').getByRole('button', { name: 'Import', exact: true });
    await expect(modal(page).getByRole('group', { name: 'Columns' })).toBeVisible({ timeout: 30_000 });
    await expect(button).toBeEnabled({ timeout: 30_000 });
    await button.click();
    await expect(modal(page).getByRole('heading', { name: action })).toBeHidden();
    await settle(page);
}

/**
 * Open the filters of the table, set them with the callback, apply them and close the panel. Choosing an option of a
 * searchable select, whose list is outside the panel, may close the panel and apply them already.
 */
export async function applyTableFilters(page: Page, setFilters: (panel: Locator) => Promise<void>): Promise<void> {
    const panel = page.locator('.fi-dropdown-panel').filter({ visible: true }).filter({ has: page.locator('.fi-ta-filters-heading') }).first();

    await page.getByRole('button', { name: 'Filter', exact: true }).click();
    await expect(panel).toBeVisible();
    await setFilters(panel);
    await settle(page);

    if (await panel.isVisible()) {
        // The list of a multiple select stays open over the button
        if (await panel.getByRole('listbox').filter({ visible: true }).count()) {
            await panel.locator('.fi-ta-filters-heading').click();
        }

        await panel.getByRole('button', { name: 'Apply filters' }).click();
    }

    await settle(page);

    // Closed, so that the next call opens it again
    if (await panel.isVisible()) {
        await page.keyboard.press('Escape');
    }

    await expect(panel).toBeHidden();
}

/**
 * Search a Filament table and wait until the search is applied (shown among its active filters): typed while the
 * page is still loading, a search can be lost, and the rows of before can look like its results.
 */
export async function searchTableUntilApplied(page: Page, text: string): Promise<void> {
    await expect(async () => {
        await searchTable(page, text);
        await expect(page.getByText(`Search: ${text}`, { exact: true }).first()).toBeVisible({ timeout: 3_000 });
    }).toPass({ timeout: 30_000 });
    await settle(page);
}
