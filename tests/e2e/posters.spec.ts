import { readFileSync, writeFileSync } from 'node:fs';
import type { Locator, Page, TestInfo } from '@playwright/test';
import { php } from './support/artisan';
import {
    applyTableFilters,
    chooseOption,
    confirmModal,
    downloadExport,
    field,
    modal,
    row,
    rowAction,
    searchTableUntilApplied,
    settle,
    uploadImport,
    visit,
} from './support/filament';
import { expect, test, uniqueName } from './support/fixtures';

/**
 * The posters of public witnessing and the places where they are kept (`app/Filament/Resources/Posters`,
 * `StorageLocations`).
 *
 * Every test works on posters and storage places of its own, so that the tests running in parallel never see each
 * other's: the seeded ones are only read.
 */

type Stock = { location: string; quantity: number };

/**
 * The PHP code deleting the records created by the test running, run after it (even when it fails), so that they don't
 * pile up over the runs.
 */
let cleanups: string[] = [];

/**
 * Delete records after the test, with PHP code (errors are ignored: the record may be gone already).
 */
const cleanUp = (code: string): void => {
    cleanups.push(`try { ${code} } catch (\\Throwable $exception) {}`);
};

test.afterEach(() => {
    if (cleanups.length > 0) {
        php(`${cleanups.join('\n')} return true;`);
        cleanups = [];
    }
});

/**
 * Create a storage place, returning its id.
 */
function createLocation(name: string): number {
    deleteLocationsLike(name);

    return php<number>(`return \\App\\Models\\StorageLocation::factory()->create(['name' => ${JSON.stringify(name)}])->id;`);
}

/**
 * Delete after the test the storage places whose name starts with the text (with the copies kept there).
 */
const deleteLocationsLike = (name: string): void =>
    cleanUp(`\\App\\Models\\StorageLocation::where('name', 'like', ${JSON.stringify(`${name}%`)})->get()->each->delete();`);

/**
 * Delete after the test the posters whose code starts with the text.
 */
const deletePostersCoded = (code: string): void =>
    cleanUp(`\\App\\Models\\Poster::where('code', 'like', ${JSON.stringify(`${code}%`)})->get()->each->delete();`);

/**
 * Create a poster with its copies in storage places (created before), returning its id.
 */
function createPoster(title: string, stocks: Stock[] = [], code: string | null = null): number {
    const id = php<number>(`
        $poster = \\App\\Models\\Poster::factory()->create(['title' => ['en' => ${JSON.stringify(title)}], 'code' => ${JSON.stringify(code)}]);

        foreach (json_decode(${JSON.stringify(JSON.stringify(stocks))}, true) as $stock) {
            $poster->stocks()->create([
                'storage_location_id' => \\App\\Models\\StorageLocation::where('name', $stock['location'])->sole()->id,
                'quantity' => $stock['quantity'],
            ]);
        }

        return $poster->id;
    `);
    cleanUp(`\\App\\Models\\Poster::find(${id})?->delete();`);

    return id;
}

/**
 * The copies of a poster in each storage place, by name.
 */
const stocksOf = (posterId: number): Record<string, number> => php(`
    return \\App\\Models\\PosterStock::where('poster_id', ${posterId})->with('storageLocation')->get()
        ->mapWithKeys(fn ($stock) => [$stock->storageLocation->name => $stock->quantity])->sortKeys()->all();
`);

/**
 * A code nobody else uses.
 */
const uniqueCode = (prefix: string): string => uniqueName(prefix).replace(' ', '-').toLowerCase();

/**
 * A copy of a fixture CSV file of `tests/e2e/fixtures`, with `{suffix}` replaced, so that each run imports records of
 * its own.
 */
function fixtureCopy(testInfo: TestInfo, fixture: string, suffix: string): string {
    const file = testInfo.outputPath(fixture);
    writeFileSync(file, readFileSync(`tests/e2e/fixtures/${fixture}`, 'utf8').replaceAll('{suffix}', suffix));

    return file;
}

/**
 * Expect a notification (toast) with the text, waiting longer than `expect()` does: saving can be slow while the other
 * tests run.
 */
const expectNotification = (page: Page, text: string | RegExp): Promise<void> =>
    expect(page.locator('.fi-no-notification').filter({ hasText: text }).first()).toBeVisible({ timeout: 30_000 });

/**
 * The rows of the quantities of the poster form, one for each storage place.
 */
const stockRows = (page: Page): Locator =>
    modal(page).getByRole('group', { name: 'Stocks' }).getByRole('row').filter({ has: page.getByRole('spinbutton') });

test.describe('Storage places', () => {
    test('creates, renames and deletes a storage place @mobile', async ({ adminPage: page }) => {
        const name = uniqueName('Storage room');
        deleteLocationsLike(name);
        const renamed = `${name} renamed`;

        await visit(page, '/app/storage-locations');
        await page.getByRole('button', { name: 'New Storage place' }).click();
        await field(modal(page), 'Name').fill(name);
        await field(modal(page), 'Notes').fill('Behind the stage');
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText('Behind the stage');
        expect(php(`return \\App\\Models\\StorageLocation::where('name', ${JSON.stringify(name)})->value('notes');`)).toBe('Behind the stage');

        await rowAction(page, name, 'Edit');
        await field(modal(page), 'Name').fill(renamed);
        await confirmModal(page, 'Save changes');
        await expectNotification(page, 'Saved');
        await expect(row(page, renamed)).toBeVisible();

        await rowAction(page, renamed, 'Delete');
        await expect(modal(page)).toContainText('The quantities of the posters kept here will be deleted too.');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');

        await expect(row(page, renamed)).toHaveCount(0);
        expect(php(`return \\App\\Models\\StorageLocation::where('name', 'like', ${JSON.stringify(`${name}%`)})->exists();`)).toBe(false);
    });

    test('refuses a storage place with the name of another one', async ({ adminPage: page }) => {
        const name = uniqueName('Taken place');
        createLocation(name);

        await visit(page, '/app/storage-locations');
        await page.getByRole('button', { name: 'New Storage place' }).click();
        await field(modal(page), 'Name').fill(name);
        await confirmModal(page, 'Create');

        await expect(modal(page)).toContainText('has already been taken');
        expect(php(`return \\App\\Models\\StorageLocation::where('name', ${JSON.stringify(name)})->count();`)).toBe(1);
    });

    test('exports the storage places as a CSV file', async ({ adminPage: page }) => {
        const name = uniqueName('Exported place');
        deleteLocationsLike(name);
        php(`return \\App\\Models\\StorageLocation::factory()->create(['name' => ${JSON.stringify(name)}, 'notes' => 'Exported note'])->id;`);

        await visit(page, '/app/storage-locations');
        const { fileName, lines: [header, ...lines] } = await downloadExport(page, 'Export Storage');

        expect(fileName).toMatch(/^export-\d+-.+\.csv$/);
        expect(header).toBe('Name,Notes');
        expect(lines).toContain(`"${name}","Exported note"`);
        expect(lines).toContainEqual(expect.stringMatching(/^"Kingdom Hall",/));
    });

    test('imports storage places from a CSV file', async ({ adminPage: page }, testInfo) => {
        const suffix = uniqueCode('s');
        deleteLocationsLike(`Imported storage ${suffix}`);

        await visit(page, '/app/storage-locations');
        await uploadImport(page, 'Import Storage', fixtureCopy(testInfo, 'storage-locations-import.csv', suffix));

        await expect.poll(() => php(`return \\App\\Models\\StorageLocation::where('name', ${JSON.stringify(`Imported storage ${suffix}`)})->value('notes');`))
            .toBe(`Imported note ${suffix}`);

        await visit(page, '/app/storage-locations');
        await searchTableUntilApplied(page, suffix);
        await expect(row(page, `Imported storage ${suffix}`)).toBeVisible();
    });
});

test.describe('Posters', () => {
    test('creates a poster with its copies in each storage place, edits and deletes it', async ({ adminPage: page }) => {
        const prefix = uniqueName('Poster place');
        const [first, second] = [`${prefix} A`, `${prefix} B`];
        createLocation(first);
        createLocation(second);
        const title = uniqueName('Poster');
        const code = uniqueCode('ps');
        deletePostersCoded(code);

        await visit(page, '/app/posters');
        await page.getByRole('button', { name: 'New Poster' }).click();
        await field(modal(page), 'Code').fill(code);
        await field(modal(page), 'English').fill(title);
        await expect(stockRows(page)).toHaveCount(1);
        await stockRows(page).nth(0).getByRole('combobox').selectOption({ label: first });
        await stockRows(page).nth(0).getByRole('spinbutton').fill('3');
        await modal(page).getByRole('button', { name: 'Add storage place' }).click();
        await expect(stockRows(page)).toHaveCount(2);
        await stockRows(page).nth(1).getByRole('combobox').selectOption({ label: second });
        await stockRows(page).nth(1).getByRole('spinbutton').fill('4');
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        const posterId = php<number>(`return \\App\\Models\\Poster::where('code', ${JSON.stringify(code)})->value('id');`);
        expect(stocksOf(posterId)).toEqual({ [first]: 3, [second]: 4 });

        await searchTableUntilApplied(page, title);
        await expect(row(page, title)).toContainText(code);
        await expect(row(page, title).locator('.fi-badge').filter({ visible: true, hasText: /^\s*7\s*$/ })).toBeVisible();

        await rowAction(page, title, 'Edit');
        await expect(stockRows(page)).toHaveCount(2);
        await stockRows(page).nth(0).getByRole('spinbutton').fill('0');
        await field(modal(page), 'Notes').fill('Torn corner');
        await confirmModal(page, 'Save changes');
        await expectNotification(page, 'Saved');

        expect(stocksOf(posterId)).toEqual({ [first]: 0, [second]: 4 });
        await expect(row(page, title)).toContainText('Torn corner');
        await expect(row(page, title).locator('.fi-badge').filter({ visible: true, hasText: /^\s*4\s*$/ })).toBeVisible();

        await rowAction(page, title, 'Delete');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');

        await expect(row(page, title)).toHaveCount(0);
        expect(php(`return [\\App\\Models\\Poster::whereKey(${posterId})->exists(), \\App\\Models\\PosterStock::where('poster_id', ${posterId})->count()];`)).toEqual([false, 0]);
    });

    test('opens the storage places from the header', async ({ adminPage: page }) => {
        const place = uniqueName('Header place');
        createLocation(place);

        await visit(page, '/app/posters');
        await page.locator('.fi-header').getByRole('link', { name: 'Storage' }).click();

        await expect(page).toHaveURL(/\/app\/storage-locations$/);
        await expect(page.getByRole('heading', { name: 'Storage', level: 1 })).toBeVisible();
        await searchTableUntilApplied(page, place);
        await expect(row(page, place)).toBeVisible();
    });

    test('filters the posters by availability and by storage place', async ({ adminPage: page }) => {
        const prefix = uniqueName('Filtered poster');
        const [kept, elsewhere] = [`${prefix} place`, `${prefix} other place`];
        createLocation(kept);
        createLocation(elsewhere);
        const [inKept, outOfStock, inElsewhere] = [`${prefix} kept`, `${prefix} out of stock`, `${prefix} elsewhere`];
        createPoster(inKept, [{ location: kept, quantity: 3 }]);
        createPoster(outOfStock, [{ location: kept, quantity: 0 }]);
        createPoster(inElsewhere, [{ location: elsewhere, quantity: 2 }]);
        const shown = page.locator('.fi-ta-row').filter({ has: page.getByRole('checkbox', { name: /^Select\/deselect item/ }) }).filter({ hasText: prefix });

        await visit(page, '/app/posters');
        await searchTableUntilApplied(page, prefix);
        await expect(shown).toHaveCount(3);

        await applyTableFilters(page, (panel) => chooseOption(panel, 'Availability', 'Out of stock'));
        await expect(shown).toHaveCount(1);
        await expect(row(page, outOfStock)).toBeVisible();

        await applyTableFilters(page, (panel) => chooseOption(panel, 'Availability', 'Available'));
        await expect(shown).toHaveCount(2);
        await expect(row(page, outOfStock)).toHaveCount(0);

        // Only the posters with copies there
        await applyTableFilters(page, async (panel) => {
            await chooseOption(panel, 'Availability', '-');
            await chooseOption(panel, 'Kept in', kept);
        });
        await expect(shown).toHaveCount(1);
        await expect(row(page, inKept)).toBeVisible();
    });

    test('exports the posters with a row for each storage place', async ({ adminPage: page }) => {
        const place = uniqueName('Export place');
        createLocation(place);
        const title = uniqueName('Exported poster');
        const code = uniqueCode('ex');
        createPoster(title, [{ location: place, quantity: 6 }], code);

        await visit(page, '/app/posters');
        const { fileName, lines: [header, ...lines] } = await downloadExport(page, 'Export Posters');

        expect(fileName).toMatch(/^export-\d+-posters\.csv$/);
        expect(header).toBe('Code,Title,"Storage place",Copies,Notes');
        expect(lines).toContainEqual(expect.stringMatching(new RegExp(`^${code},"${title}","${place}",6,`)));
        expect(lines).toContainEqual(expect.stringMatching(/^demo-01,"Demo poster 1","Kingdom Hall",2,/));
    });

    test('imports the posters, creating the missing storage places', async ({ adminPage: page }, testInfo) => {
        const suffix = uniqueCode('p');
        deletePostersCoded(`imp-${suffix}`);
        deleteLocationsLike(`Imported place ${suffix}`);

        await visit(page, '/app/posters');
        await uploadImport(page, 'Import Posters', fixtureCopy(testInfo, 'posters-import.csv', suffix));

        await expect.poll(() => php(`
            $poster = \\App\\Models\\Poster::where('code', ${JSON.stringify(`imp-${suffix}`)})->first();

            return $poster ? ['title' => $poster->getTranslations('title'), 'notes' => $poster->notes] : null;
        `)).toEqual({ title: { en: `Imported poster ${suffix}` }, notes: `Imported note ${suffix}` });

        const posterId = php<number>(`return \\App\\Models\\Poster::where('code', ${JSON.stringify(`imp-${suffix}`)})->value('id');`);
        expect(stocksOf(posterId)).toEqual({ [`Imported place ${suffix} A`]: 2, [`Imported place ${suffix} B`]: 5 });

        await visit(page, '/app/posters');
        await searchTableUntilApplied(page, `Imported poster ${suffix}`);
        await expect(row(page, `Imported poster ${suffix}`).locator('.fi-badge').filter({ visible: true, hasText: /^\s*7\s*$/ })).toBeVisible();
    });
});
