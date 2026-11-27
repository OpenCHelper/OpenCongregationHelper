import type { Page } from '@playwright/test';
import { php } from './support/artisan';
import { chooseOption, confirmModal, expectNotification, field, modal, row, rowAction, searchTableUntilApplied, settle, visit } from './support/filament';
import { expect, test, uniqueName } from './support/fixtures';

/**
 * The zones of the territories (`app/Filament/Resources/TerritoryZones`), and the territories page of a zone.
 */

/**
 * Create a zone with territories of the given names (and tags of its own), returning the ids of the zone and of the
 * territories.
 */
function createZone(name: string, options: { territories?: string[]; tags?: string[] } = {}): { zoneId: number; ids: Record<string, number> } {
    return php(`
        $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(name)}]);

        foreach (json_decode(${JSON.stringify(JSON.stringify(options.tags ?? []))}, true) as $tag) {
            $zone->attachTag($tag, \\App\\Enums\\TagType::ZONES->value);
        }

        $ids = [];
        foreach (json_decode(${JSON.stringify(JSON.stringify(options.territories ?? []))}, true) as $territory) {
            $ids[$territory] = \\App\\Models\\Territory::factory()->for($zone)->create(['name' => $territory])->id;
        }

        return ['zoneId' => $zone->id, 'ids' => $ids];
    `);
}

/**
 * Whether a zone is deleted (soft), or null when it is permanently deleted.
 */
const isZoneTrashed = (id: number): boolean | null =>
    php(`return \\App\\Models\\TerritoryZone::withTrashed()->find(${id})?->trashed();`);

/**
 * Set a filter of the table and apply it.
 */
async function applyFilter(page: Page, label: string, option: string): Promise<void> {
    const apply = page.getByRole('button', { name: 'Apply filters' });

    // Clicked while the page is still loading, the button may not open the panel yet
    await expect(async () => {
        if (!(await apply.isVisible())) {
            await page.getByRole('button', { name: 'Filter', exact: true }).click();
        }

        await expect(apply).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 20_000 });

    await chooseOption(page.locator('.fi-dropdown-panel').filter({ visible: true }), label, option);
    // Close the list of options of a multiple select, which stays open over the button
    await page.getByRole('heading', { name: 'Filters' }).click();
    await apply.click();
    await expect(apply).toBeHidden();
    await settle(page);
}

/** The rows of the records of the table (not the individual search row). */
const tableRows = (page: Page) => page.locator('.fi-ta-row:has(.fi-ta-record-checkbox)');

test.describe.configure({ mode: 'parallel' });

test.describe('Zones', () => {
    test('creates a zone with a color and tags', async ({ adminPage: page }) => {
        const name = uniqueName('Zone');
        const tag = uniqueName('Zone tag');

        await visit(page, '/app/territory-zones/create');
        await field(page, 'Name').fill(name);
        await page.getByRole('combobox', { name: 'Color' }).fill('#aa3300');
        await page.getByRole('combobox', { name: 'Tags' }).fill(tag);
        await page.getByRole('combobox', { name: 'Tags' }).press('Enter');
        await page.getByRole('button', { name: 'Create', exact: true }).and(page.locator('[type="submit"]')).click();

        await expectNotification(page, 'Created');
        await expect(page.getByRole('heading', { level: 1 })).toContainText(name);
        expect(php(`
            $zone = \\App\\Models\\TerritoryZone::where('name', ${JSON.stringify(name)})->sole();

            return ['color' => $zone->color, 'tags' => $zone->tags->pluck('name')->all()];
        `)).toEqual({ color: '#aa3300', tags: [tag] });
    });

    test('lists the zones with their number of territories, and filters them by tag', async ({ adminPage: page }) => {
        const prefix = uniqueName('Listed');
        const tag = uniqueName('Zone tag');
        createZone(`${prefix} Tagged`, { tags: [tag], territories: [`${prefix} T1`, `${prefix} T2`] });
        createZone(`${prefix} Untagged`);

        await visit(page, '/app/territory-zones');
        await searchTableUntilApplied(page, prefix);
        await expect(tableRows(page)).toHaveCount(2);
        await expect(row(page, `${prefix} Tagged`)).toContainText('2');
        await expect(row(page, `${prefix} Tagged`)).toContainText(tag);

        await applyFilter(page, 'Tags', tag);
        await expect(tableRows(page)).toHaveCount(1);
        await expect(row(page, `${prefix} Tagged`)).toBeVisible();
    });

    test('edits a zone', async ({ adminPage: page }) => {
        const name = uniqueName('Zone');
        const { zoneId } = createZone(name);

        await visit(page, `/app/territory-zones/${zoneId}/edit`);
        await field(page, 'Name').fill(`${name} renamed`);
        await page.getByRole('button', { name: 'Save changes' }).click();

        await expectNotification(page, 'Saved');
        expect(php(`return \\App\\Models\\TerritoryZone::find(${zoneId})->name;`)).toBe(`${name} renamed`);
    });

    test('deletes an empty zone, restores it, then deletes it permanently', async ({ adminPage: page }) => {
        const name = uniqueName('Empty Zone');
        const { zoneId } = createZone(name);

        await visit(page, `/app/territory-zones/${zoneId}`);
        await page.locator('.fi-header').getByRole('button', { name: 'Delete' }).click();
        await confirmModal(page, 'Delete');
        await expect(page).toHaveURL(/\/app\/territory-zones$/);
        expect(isZoneTrashed(zoneId)).toBe(true);

        await searchTableUntilApplied(page, name);
        await expect(tableRows(page)).toHaveCount(0);
        await applyFilter(page, 'Deleted records', 'Only deleted records');
        await expect(tableRows(page)).toHaveCount(1);
        await rowAction(page, name, 'Restore');
        await confirmModal(page, 'Restore');
        await expectNotification(page, 'Restored');
        expect(isZoneTrashed(zoneId)).toBe(false);

        // Only a deleted zone can be deleted permanently
        php(`\\App\\Models\\TerritoryZone::find(${zoneId})->delete(); return true;`);
        await page.reload();
        await settle(page);
        await expect(row(page, name).getByRole('button', { name: 'Force delete' })).toBeVisible();
        await rowAction(page, name, 'Force delete');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');
        expect(isZoneTrashed(zoneId)).toBeNull();
    });

    test('refuses to delete a zone that still has territories', async ({ adminPage: page }) => {
        const name = uniqueName('Full Zone');
        const { zoneId } = createZone(name, { territories: [`${name} T1`] });

        await visit(page, `/app/territory-zones/${zoneId}`);
        await page.locator('.fi-header').getByRole('button', { name: 'Delete' }).click();
        await confirmModal(page, 'Delete');

        await expectNotification(page, 'Zone not deleted');
        await expectNotification(page, 'This zone still contains territories');
        expect(isZoneTrashed(zoneId)).toBe(false);
    });
});

test.describe('Territories of a zone', () => {
    test('creates a territory from the territories page of the zone', async ({ adminPage: page }) => {
        const zone = uniqueName('Zone');
        const { zoneId } = createZone(zone, { territories: [`${zone} Existing`] });
        const name = `${zone} New`;

        await visit(page, `/app/territory-zones/${zoneId}/territories`);
        await expect(page.getByRole('heading', { level: 1, name: `Territories of zone ${zone}` })).toBeVisible();
        await expect(tableRows(page)).toHaveCount(1);

        await page.getByRole('button', { name: 'New territory' }).click();
        await field(modal(page), 'Name').fill(name);
        await chooseOption(modal(page), 'Zone', zone);
        await field(modal(page), 'Notes').fill('Created from the zone');
        await confirmModal(page, 'Create');

        await expectNotification(page, 'Created');
        await expect(tableRows(page)).toHaveCount(2);
        await expect(row(page, name)).toBeVisible();
        expect(php(`return \\App\\Models\\Territory::where('name', ${JSON.stringify(name)})->sole()->only(['territory_zone_id', 'notes']);`))
            .toEqual({ territory_zone_id: zoneId, notes: 'Created from the zone' });
    });

    test('deletes a territory of the zone and restores it', async ({ adminPage: page }) => {
        const zone = uniqueName('Zone');
        const name = `${zone} Removed`;
        const { zoneId, ids } = createZone(zone, { territories: [name] });
        const isTrashed = (): boolean => php(`return \\App\\Models\\Territory::withTrashed()->find(${ids[name]})->trashed();`);

        await visit(page, `/app/territory-zones/${zoneId}/territories`);
        await expect(tableRows(page)).toHaveCount(1);
        await rowAction(page, name, 'Delete');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');
        await expect(tableRows(page)).toHaveCount(0);
        expect(isTrashed()).toBe(true);

        await applyFilter(page, 'Deleted records', 'With deleted records');
        await expect(row(page, name).getByRole('button', { name: 'Restore' })).toBeVisible();
        await rowAction(page, name, 'Restore');
        await confirmModal(page, 'Restore');
        await expectNotification(page, 'Restored');
        expect(isTrashed()).toBe(false);
    });
});
