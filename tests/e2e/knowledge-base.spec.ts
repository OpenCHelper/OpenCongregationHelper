import { type Page } from '@playwright/test';
import { test, expect } from './support/fixtures';
import { modal, settle, visit } from './support/filament';

/**
 * The sidebar of the knowledge base, with the sections (groups) and the articles of `docs/user-documentation`.
 */
const sidebar = (page: Page) => page.getByRole('navigation', { name: 'Sidebar navigation' });

/**
 * The title of the article shown.
 */
const articleTitle = (page: Page) => page.getByRole('main').getByRole('heading', { level: 1 }).first();

test.describe('Knowledge base', () => {
    test('opens on the getting started article, without signing in @mobile', async ({ page }) => {
        await visit(page, '/knowledge-base');

        await expect(page).toHaveURL(/\/knowledge-base\/getting-started$/);
        await expect(articleTitle(page)).toHaveText('Getting started');
        await expect(page.getByRole('main').getByRole('article')).toContainText('Open Source');
    });

    test('lists the sections and their articles, and opens them from the sidebar', async ({ page }) => {
        await visit(page, '/knowledge-base');

        for (const section of ['Basics', 'Territories', 'Assignments', 'Public Witnessing', 'Publications', 'Administration', 'Guides']) {
            await expect(sidebar(page).getByRole('button', { name: section, exact: true })).toBeVisible();
        }

        await sidebar(page).getByRole('link', { name: 'Zones', exact: true }).click();
        await expect(page).toHaveURL(/\/knowledge-base\/territories\/zones$/);
        await expect(articleTitle(page)).toHaveText('Zones');

        await sidebar(page).getByRole('link', { name: 'Assigning and returning' }).click();
        await expect(page).toHaveURL(/\/knowledge-base\/assignments\/assigning$/);
        await expect(articleTitle(page)).toHaveText('Assigning and returning');
        await expect(page.getByRole('main').getByRole('navigation', { name: 'Breadcrumb' })).toContainText('Assigning and returning');
    });

    test('collapses a section of the sidebar', async ({ page }) => {
        await visit(page, '/knowledge-base');

        const section = sidebar(page).getByRole('button', { name: 'Guides', exact: true });
        const article = sidebar(page).getByRole('link', { name: 'Running a campaign' });
        await expect(section).toHaveAttribute('aria-expanded', 'true');
        await expect(article).toBeVisible();

        await section.click();
        await expect(section).toHaveAttribute('aria-expanded', 'false');
        await expect(article).toBeHidden();
    });

    test('follows the links between articles', async ({ page }) => {
        await visit(page, '/knowledge-base/territories/introduction');

        await page.getByRole('main').getByRole('article').getByRole('link', { name: 'Importing territories' }).click();

        await expect(page).toHaveURL(/\/knowledge-base\/territories\/importing$/);
        await expect(articleTitle(page)).toHaveText('Importing territories');
    });

    test('finds articles with the search', async ({ page }) => {
        await visit(page, '/knowledge-base');

        await page.getByRole('searchbox', { name: 'Global search' }).fill('campaign');
        const results = page.getByRole('region', { name: 'Global search results' });
        await expect(results.getByRole('link', { name: 'Running a campaign' })).toBeVisible();

        await results.getByRole('link', { name: 'Running a campaign' }).click();
        await expect(page).toHaveURL(/\/knowledge-base\/guides\/running-a-campaign$/);
        await expect(articleTitle(page)).toHaveText('Running a campaign');
    });

    test('switches to the Italian documentation', async ({ page }) => {
        await visit(page, '/knowledge-base/territories/zones');
        await expect(articleTitle(page)).toHaveText('Zones');

        await page.getByRole('button', { name: 'English' }).click();
        await page.getByRole('menuitem', { name: /Italian/ }).click();
        await settle(page);

        await expect(page.getByRole('button', { name: 'Italiano' }).or(page.getByRole('button', { name: 'Italian' }))).toBeVisible();
        await expect(sidebar(page).getByRole('button', { name: 'Territori', exact: true })).toBeVisible();
        await expect(sidebar(page).getByRole('link', { name: 'Introduzione' })).toBeVisible();

        await visit(page, '/knowledge-base/territories/zones');
        await expect(articleTitle(page)).toHaveText('Zone');
    });

    test('leads guests back to the welcome page', async ({ page }) => {
        await visit(page, '/knowledge-base/getting-started');

        await page.getByRole('link', { name: 'Back' }).click();
        await expect(page).toHaveURL(/127\.0\.0\.1:\d+\/$/);
    });
});

test.describe('Documentation from the app', () => {
    test('opens the knowledge base from the sidebar, and goes back to the app', async ({ adminPage }) => {
        await visit(adminPage, '/app');

        await adminPage.locator('.fi-sidebar').getByRole('link', { name: 'Documentation', exact: true }).click();
        await expect(adminPage).toHaveURL(/\/knowledge-base/);
        await expect(articleTitle(adminPage)).toHaveText('Getting started');

        await adminPage.getByRole('link', { name: 'Back' }).click();
        await expect(adminPage).toHaveURL(/\/app$/);
    });

    test('previews the documentation of a page in a slide-over from the help menu', async ({ adminPage }) => {
        await visit(adminPage, '/app/territories');

        await adminPage.getByRole('navigation', { name: 'Topbar' }).getByRole('button', { name: 'Help' }).click();

        const preview = modal(adminPage);
        await expect(preview).toBeVisible();
        await expect(adminPage.locator('.fi-modal.fi-modal-open')).toHaveClass(/fi-modal-slide-over/);
        await expect(preview.getByRole('navigation', { name: 'Breadcrumb' })).toContainText('Territories list');
        await expect(preview.getByRole('article')).toContainText('Territories are the backbone of this project');
        // The page behind stays where it was
        await expect(adminPage).toHaveURL(/\/app\/territories$/);

        await preview.getByRole('button', { name: 'Close' }).first().click();
        await expect(adminPage.locator('.fi-modal.fi-modal-open')).toHaveCount(0);
    });
});
