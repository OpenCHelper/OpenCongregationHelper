import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { php } from './support/artisan';
import { bulkMenuAction, chooseOption, confirmModal, expectNotification, field, modal, row, rowMenuAction, searchTableUntilApplied, settle, visit } from './support/filament';
import { expect, test, uniqueName, users } from './support/fixtures';

/**
 * The territories (`app/Filament/Resources/Territories`): their list, form, pages and import.
 *
 * Every test works on territories of a zone of its own, so that the tests running in parallel never see each other's.
 */

const polygon = JSON.stringify({
    type: 'FeatureCollection',
    features: [{
        type: 'Feature',
        properties: {},
        geometry: { type: 'Polygon', coordinates: [[[12.40, 41.90], [12.45, 41.90], [12.42, 41.94], [12.40, 41.90]]] },
    }],
});

type TerritoryAttributes = { name: string; is_commercial?: boolean; geojson?: string | null; notes?: string; tags?: string[]; assigned?: boolean; deleted?: boolean };

/**
 * Create a zone with the territories, returning the ids of the zone and of the territories (by name).
 */
function createZoneWith(zoneName: string, territories: TerritoryAttributes[]): { zoneId: number; ids: Record<string, number> } {
    return php(`
        $zone = \\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(zoneName)}]);
        $publisher = \\App\\Models\\User::where('email', ${JSON.stringify(users.publisher.email)})->sole();
        $ids = [];

        foreach (json_decode(${JSON.stringify(JSON.stringify(territories))}, true) as $attributes) {
            $territory = \\App\\Models\\Territory::factory()->for($zone)->create([
                'name' => $attributes['name'],
                'is_commercial' => $attributes['is_commercial'] ?? false,
                'geojson' => $attributes['geojson'] ?? null,
                'notes' => $attributes['notes'] ?? 'Notes',
            ]);

            foreach ($attributes['tags'] ?? [] as $tag) {
                $territory->attachTag($tag, \\App\\Enums\\TagType::TERRITORIES->value);
            }

            if ($attributes['assigned'] ?? false) {
                $territory->assignments()->create(['assignee_id' => $publisher->id, 'started_at' => now()->subDays(3)]);
            }

            if ($attributes['deleted'] ?? false) {
                $territory->delete();
            }

            $ids[$attributes['name']] = $territory->id;
        }

        return ['zoneId' => $zone->id, 'ids' => $ids];
    `);
}

/**
 * Open the territories list, showing only the territories whose name contains the text.
 */
async function listTerritories(page: Page, search: string): Promise<void> {
    await visit(page, '/app/territories');
    await searchTableUntilApplied(page, search);
}

/**
 * Set a filter of the territories table and apply it.
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
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.getByRole('button', { name: 'Apply filters' })).toBeHidden();
    await settle(page);
}

/** The rows of the records of the table (not the individual search row, nor the group headers). */
/**
 * Submit the form of the page with its button (not the buttons of its fields, e.g. the one creating a zone).
 */
async function submitForm(page: Page, label: string): Promise<void> {
    await page.getByRole('button', { name: label, exact: true }).and(page.locator('[type="submit"]')).filter({ visible: true }).first().click();
}

const territoryRows = (page: Page) => page.locator('.fi-ta-row:has(.fi-ta-record-checkbox)');

test.describe.configure({ mode: 'parallel' });

test.describe('Territories list', () => {
    test('has tabs for any, residential and commercial territories, with their counts', async ({ adminPage: page }) => {
        await visit(page, '/app/territories');

        const tabs = page.getByRole('group', { name: 'Filter records' });
        const countOf = async (tab: string): Promise<number> =>
            Number((await tabs.getByRole('button', { name: new RegExp(`^${tab} \\d+$`) }).innerText()).replace(/\D/g, ''));

        const [any, residential, commercial] = [await countOf('Any'), await countOf('Residential'), await countOf('Commercial')];
        expect(residential).toBeGreaterThan(0);
        expect(commercial).toBeGreaterThan(0);
        expect(any).toBe(residential + commercial);

        await searchTableUntilApplied(page, 'E2E');
        await expect(row(page, 'E2E Commercial')).toBeVisible();
        await expect(row(page, 'E2E Free')).toBeVisible();

        await tabs.getByRole('button', { name: /^Commercial/ }).click();
        await settle(page);
        await expect(row(page, 'E2E Commercial')).toBeVisible();
        await expect(row(page, 'E2E Free')).toHaveCount(0);

        await tabs.getByRole('button', { name: /^Residential/ }).click();
        await settle(page);
        await expect(row(page, 'E2E Free')).toBeVisible();
        await expect(row(page, 'E2E Commercial')).toHaveCount(0);
    });

    test('searches the territories by name', async ({ adminPage: page }) => {
        const zone = uniqueName('Search Zone');
        createZoneWith(zone, [{ name: `${zone} Alpha` }, { name: `${zone} Beta` }]);

        await listTerritories(page, `${zone} Alpha`);

        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, `${zone} Alpha`)).toContainText(zone);
    });

    test('filters the territories by assignment status, map and tags', async ({ adminPage: page }) => {
        const zone = uniqueName('Filter Zone');
        const tag = uniqueName('Tag');
        createZoneWith(zone, [
            { name: `${zone} Assigned`, assigned: true },
            { name: `${zone} Mapped`, geojson: polygon, tags: [tag] },
            { name: `${zone} Plain` },
        ]);

        await listTerritories(page, zone);
        await expect(territoryRows(page)).toHaveCount(3);
        await expect(row(page, `${zone} Assigned`)).toContainText(users.publisher.name);

        await applyFilter(page, 'Assignment Status', 'Assigned');
        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, `${zone} Assigned`)).toBeVisible();

        await applyFilter(page, 'Assignment Status', 'Unassigned');
        await expect(territoryRows(page)).toHaveCount(2);
        await expect(row(page, `${zone} Assigned`)).toHaveCount(0);

        await applyFilter(page, 'Map State', 'Available');
        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, `${zone} Mapped`)).toBeVisible();

        await page.getByRole('button', { name: 'Filter', exact: true }).click();
        await page.getByRole('button', { name: 'Reset' }).click();
        await settle(page);
        await searchTableUntilApplied(page, zone);
        await expect(territoryRows(page)).toHaveCount(3);

        await applyFilter(page, 'Tags', tag);
        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, `${zone} Mapped`)).toContainText(tag);
    });

    test('shows the deleted territories only with the trashed filter', async ({ adminPage: page }) => {
        const zone = uniqueName('Trash Zone');
        createZoneWith(zone, [{ name: `${zone} Kept` }, { name: `${zone} Gone`, deleted: true }]);

        await listTerritories(page, zone);
        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, `${zone} Gone`)).toHaveCount(0);

        await applyFilter(page, 'Deleted records', 'With deleted records');
        await expect(territoryRows(page)).toHaveCount(2);

        await applyFilter(page, 'Deleted records', 'Only deleted records');
        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, `${zone} Gone`)).toBeVisible();
    });

    test('groups the territories by zone, with the number of territories of each', async ({ adminPage: page }) => {
        const zone = uniqueName('Group Zone');
        createZoneWith(zone, [{ name: `${zone} One` }, { name: `${zone} Two` }]);

        await listTerritories(page, zone);
        await page.getByRole('combobox', { name: 'Group by' }).selectOption({ label: 'Zone' });
        await settle(page);

        await expect(page.locator('.fi-ta-group-header').filter({ hasText: `${zone} (2)` })).toBeVisible();
        await expect(territoryRows(page)).toHaveCount(2);
    });
});

test.describe('Territory form', () => {
    test('creates a territory with a map, an image, notes and tags @mobile', async ({ adminPage: page }) => {
        const zone = uniqueName('Form Zone');
        createZoneWith(zone, []);
        const name = uniqueName('Created Territory');
        const tag = uniqueName('Form Tag');

        await visit(page, '/app/territories/create');
        await field(page, 'Name').fill(name);
        await chooseOption(page, 'Zone', zone);
        await page.getByRole('switch', { name: 'Commercial?' }).click();
        await field(page, 'Notes').fill('Ring the bell twice');
        await page.getByRole('combobox', { name: 'Tags' }).fill(tag);
        await page.getByRole('combobox', { name: 'Tags' }).press('Enter');

        // Draw a triangle with the polygon tool of the map editor
        await page.locator('[title="Draw Polygons"] .leaflet-buttons-control-button').click();
        const map = await page.locator('.leaflet-container').first().boundingBox();
        for (const [x, y] of [[0.3, 0.3], [0.7, 0.3], [0.5, 0.7], [0.3, 0.3]]) {
            await page.mouse.click(map!.x + map!.width * x, map!.y + map!.height * y);
        }
        await expect(field(page, 'Geojson')).toHaveValue(/"Polygon"/);

        await page.getByRole('tab', { name: 'Image' }).click();
        const imageTab = page.getByRole('tabpanel', { name: 'Image' });
        await expect(imageTab.getByText('Drag & Drop your files')).toBeVisible();
        await imageTab.locator('input[type="file"]').setInputFiles('tests/e2e/fixtures/territory.png');
        await expect(imageTab.getByRole('alert')).toContainText('Upload complete');
        await expect(imageTab.getByRole('img', { name })).toBeVisible();

        await submitForm(page, 'Create');
        await expect(page).toHaveURL(/\/app\/territories\/\d+$/);
        await expect(page.getByRole('heading', { level: 1, name: `View ${name}` })).toBeVisible();

        const saved = php<{ zone: string; is_commercial: boolean; notes: string; tags: string[]; geojson: string; image_path: string }>(`
            $territory = \\App\\Models\\Territory::with('territoryZone')->where('name', ${JSON.stringify(name)})->sole();

            return [
                'zone' => $territory->territoryZone->name,
                'is_commercial' => $territory->is_commercial,
                'notes' => $territory->notes,
                'tags' => $territory->tags->pluck('name')->all(),
                'geojson' => $territory->geojson,
                'image_path' => $territory->image_path,
            ];
        `);
        expect(saved).toMatchObject({ zone, is_commercial: true, notes: 'Ring the bell twice', tags: [tag] });
        expect(JSON.parse(saved.geojson).features[0].geometry.type).toBe('Polygon');
        expect(saved.image_path).toMatch(/^territories\/.+\.png$/);
    });

    test('refuses a name already used in the same zone', async ({ adminPage: page }) => {
        const zone = uniqueName('Unique Zone');
        createZoneWith(zone, [{ name: `${zone} Taken` }]);

        await visit(page, '/app/territories/create');
        await field(page, 'Name').fill(`${zone} Taken`);
        await chooseOption(page, 'Zone', zone);
        await submitForm(page, 'Create');

        await expect(page.getByText('A territory with this name already exists in this zone.')).toBeVisible();
        await expect(page).toHaveURL(/\/create$/);
    });

    test('shows the details and the map of a territory on its page @mobile', async ({ adminPage: page }) => {
        const zone = uniqueName('View Zone');
        const name = `${zone} Viewed`;
        const { ids } = createZoneWith(zone, [{ name, geojson: polygon, notes: 'Beware of the dog', tags: ['Viewed tag'] }]);

        await visit(page, `/app/territories/${ids[name]}`);

        await expect(page.getByRole('heading', { level: 1, name: `View ${name}` })).toBeVisible();
        await expect(field(page, 'Name')).toHaveValue(name);
        await expect(page.getByRole('button', { name: 'Zone' })).toHaveText(zone);
        await expect(field(page, 'Notes')).toHaveValue('Beware of the dog');
        await expect(page.getByRole('group', { name: 'Tags' })).toContainText('Viewed tag');
        await expect(page.getByText('Never covered yet')).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Map' })).toBeVisible();
        // The map is drawn once it is scrolled into view (below the fold on a phone, see `filament-map-picker-fix.js`)
        await page.getByRole('tabpanel', { name: 'Map' }).scrollIntoViewIfNeeded();
        await expect(page.locator('.leaflet-overlay-pane path').first()).toBeVisible();
    });

    test('edits a territory', async ({ adminPage: page }) => {
        const zone = uniqueName('Edit Zone');
        const name = `${zone} Before`;
        const { ids } = createZoneWith(zone, [{ name }]);

        await visit(page, `/app/territories/${ids[name]}/edit`);
        await field(page, 'Name').fill(`${zone} After`);
        await page.getByRole('switch', { name: 'Commercial?' }).click();
        await field(page, 'Notes').fill('Edited notes');
        await page.getByRole('button', { name: 'Save changes' }).click();

        await expectNotification(page, 'Saved');
        expect(php(`return \\App\\Models\\Territory::find(${ids[name]})->only(['name', 'is_commercial', 'notes']);`)).toEqual({
            name: `${zone} After`,
            is_commercial: true,
            notes: 'Edited notes',
        });
    });
});

test.describe('Territory deletion', () => {
    test('refuses to delete a territory that is still assigned', async ({ adminPage: page }) => {
        const zone = uniqueName('Guard Zone');
        const name = `${zone} Out`;
        const { ids } = createZoneWith(zone, [{ name, assigned: true }]);

        await visit(page, `/app/territories/${ids[name]}/edit`);
        await page.locator('.fi-header').getByRole('button', { name: 'Delete' }).click();
        await confirmModal(page, 'Delete');

        await expectNotification(page, 'Territory not deleted');
        await expectNotification(page, 'This territory is still assigned');
        expect(php(`return \\App\\Models\\Territory::withTrashed()->find(${ids[name]})->trashed();`)).toBe(false);
    });

    test('deletes a territory, restores it, then deletes it permanently', async ({ adminPage: page }) => {
        const zone = uniqueName('Delete Zone');
        const name = `${zone} Doomed`;
        const { ids } = createZoneWith(zone, [{ name }]);
        const isTrashed = (): boolean | null =>
            php(`return \\App\\Models\\Territory::withTrashed()->find(${ids[name]})?->trashed();`);

        await visit(page, `/app/territories/${ids[name]}/edit`);
        await page.locator('.fi-header').getByRole('button', { name: 'Delete' }).click();
        await confirmModal(page, 'Delete');
        await expect(page).toHaveURL(/\/app\/territories$/);
        await expectNotification(page, 'Deleted');
        expect(isTrashed()).toBe(true);

        await searchTableUntilApplied(page, zone);
        await applyFilter(page, 'Deleted records', 'Only deleted records');
        await rowMenuAction(page, name, 'Restore');
        await confirmModal(page, 'Restore');
        await expectNotification(page, 'Restored');
        expect(isTrashed()).toBe(false);

        await applyFilter(page, 'Deleted records', 'With deleted records');
        await bulkMenuAction(page, [name], 'Delete selected');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');
        expect(isTrashed()).toBe(true);

        await rowMenuAction(page, name, 'Force delete');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');
        expect(isTrashed()).toBeNull();
    });
});

test.describe('Territory import', () => {
    test('imports the territories of a CSV file', async ({ adminPage: page }) => {
        const zone = uniqueName('Import Zone');
        const prefix = uniqueName('Imported');
        const csv = readFileSync('tests/e2e/fixtures/territories-import.csv', 'utf8')
            .replaceAll('{prefix}', prefix)
            .replaceAll('{zone}', zone);

        await visit(page, '/app/territories');
        await page.getByRole('button', { name: 'Import territories' }).click();
        await expect(modal(page).getByText('Upload a CSV file')).toBeVisible();
        await modal(page).locator('input[type="file"]').setInputFiles({ name: 'territories.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) });
        await expect(modal(page).getByRole('alert')).toContainText('Upload complete');
        await expect(field(modal(page), 'Zone')).toHaveValue('territoryZone');
        await confirmModal(page, 'Import');
        await expectNotification(page, /import/i);

        await expect.poll(() => php(`
            return \\App\\Models\\Territory::with('territoryZone')->where('name', 'like', ${JSON.stringify(`${prefix}%`)})->orderBy('name')->get()
                ->map(fn ($territory) => [$territory->name, $territory->territoryZone->name, $territory->is_commercial, $territory->notes])->all();
        `)).toEqual([
            [`${prefix} Market`, zone, true, 'Shops only'],
            [`${prefix} North`, zone, false, 'Ring twice'],
        ]);

        await listTerritories(page, prefix);
        await expect(territoryRows(page)).toHaveCount(2);
        await expect(row(page, `${prefix} North`)).toContainText(zone);
    });
});

test.describe('Territory assignment history', () => {
    test('lists the past and current assignments of a territory', async ({ adminPage: page }) => {
        const zone = uniqueName('History Zone');
        const name = `${zone} Worked`;
        const { ids } = createZoneWith(zone, [{ name }]);
        php(`
            $territory = \\App\\Models\\Territory::find(${ids[name]});
            $servant = \\App\\Models\\User::where('email', ${JSON.stringify(users.servant.email)})->sole();
            $publisher = \\App\\Models\\User::where('email', ${JSON.stringify(users.publisher.email)})->sole();
            $territory->assignments()->create(['assignee_id' => $servant->id, 'started_at' => now()->subDays(40), 'ended_at' => now()->subDays(20)]);
            $territory->assignments()->create(['assignee_id' => $publisher->id, 'started_at' => now()->subDays(5)]);

            return true;
        `);

        await visit(page, `/app/territories/${ids[name]}`);
        await page.getByRole('navigation', { name: 'Tabs' }).getByRole('link', { name: /Assignment History/ }).click();

        await expect(page).toHaveURL(new RegExp(`/app/territories/${ids[name]}/assignments$`));
        await expect(page.getByRole('heading', { level: 1, name: `Assignment history of ${name}` })).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Tabs' }).getByRole('link', { name: /Assignment History/ })).toContainText('2');
        // Only the assignments in progress, by default
        await expect(territoryRows(page)).toHaveCount(1);
        await expect(row(page, users.publisher.name)).toContainText('(in progress)');

        await applyFilter(page, 'Assignment Status', 'All statuses');
        await expect(territoryRows(page)).toHaveCount(2);
        await expect(row(page, users.servant.name)).not.toContainText('(in progress)');
    });
});
