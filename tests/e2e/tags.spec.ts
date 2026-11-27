import type { Page } from '@playwright/test';
import { appURL } from '../../playwright.config';
import { php } from './support/artisan';
import { applyTableFilters, chooseOption, confirmModal, field, modal, row, rowAction, searchTableUntilApplied, settle, visit } from './support/filament';
import { expect, test, uniqueName } from './support/fixtures';
import { storageStateOf } from './support/users';

/**
 * The tags of the territories and zones (`app/Filament/Resources/Tags`).
 *
 * Every test works on tags (and territories) of its own, so that the tests running in parallel never see each other's:
 * the seeded ones are only read.
 */

type TagRecord = { name: Record<string, string>; slug: Record<string, string>; type: string | null };

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
 * A tag name nobody else uses, in lowercase like the slug made from it.
 */
function uniqueTag(prefix: string): string {
    const name = uniqueName(prefix).toLowerCase();
    cleanUp(`\\App\\Models\\Tag::where('name->en', 'like', ${JSON.stringify(`${name}%`)})->get()->each->delete();`);

    return name;
}

/**
 * Create a tag of a type, returning its id.
 */
const createTag = (name: string, type: 'territories' | 'zones' | null = 'territories'): number =>
    php<number>(`return \\App\\Models\\Tag::findOrCreate(${JSON.stringify(name)}, ${JSON.stringify(type)})->id;`);

/**
 * The tag with the name (in the tags locale), as stored.
 */
const tagNamed = (name: string): TagRecord | null => php(`
    $tag = \\App\\Models\\Tag::query()->where('name->en', ${JSON.stringify(name)})->first();

    return $tag ? ['name' => $tag->getTranslations('name'), 'slug' => $tag->getTranslations('slug'), 'type' => $tag->type] : null;
`);

/**
 * Create a territory of its own zone with the tags, returning its id.
 */
function createTerritory(name: string, tags: string[]): number {
    const id = php<number>(`
    $territory = \\App\\Models\\Territory::factory()
        ->for(\\App\\Models\\TerritoryZone::factory()->create(['name' => ${JSON.stringify(`${name} zone`)}]))
        ->create(['name' => ${JSON.stringify(name)}]);
    $territory->attachTags(${JSON.stringify(tags)}, 'territories');

    return $territory->id;
`);
    cleanUp(`$territory = \\App\\Models\\Territory::withTrashed()->find(${id}); $zone = $territory?->territory_zone_id; $territory?->forceDelete(); \\App\\Models\\TerritoryZone::whereKey($zone)->delete();`);

    return id;
}

/**
 * Expect a notification (toast) with the text, waiting longer than `expect()` does: saving can be slow while the other
 * tests run.
 */
const expectNotification = (page: Page, text: string | RegExp): Promise<void> =>
    expect(page.locator('.fi-no-notification').filter({ hasText: text }).first()).toBeVisible({ timeout: 30_000 });

test.describe('Tags', () => {
    test('creates a tag with a slug made from its name, edits and deletes it @mobile', async ({ adminPage: page }) => {
        const name = uniqueTag('Gated community');
        const slug = name.replace(/ /g, '-');

        await visit(page, '/app/tags');
        await page.getByRole('button', { name: 'New Tag' }).click();
        await field(modal(page), 'Name').fill(name);
        await field(modal(page), 'Name').blur();
        await expect(field(modal(page), 'Slug')).toHaveValue(slug);
        await chooseOption(modal(page), 'Type', 'Territories');
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        // Stored in the tags locale only, whatever the language of the interface
        expect(tagNamed(name)).toEqual({ name: { en: name }, slug: { en: slug }, type: 'territories' });

        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText(slug);
        await expect(row(page, name)).toContainText('Territories');

        await rowAction(page, name, 'Edit');
        await field(modal(page), 'Name').fill(`${name} edited`);
        await field(modal(page), 'Name').blur();
        await settle(page);
        // The form fills it in only for a new tag...
        await expect(field(modal(page), 'Slug')).toHaveValue(slug);
        await confirmModal(page, 'Save changes');
        await expectNotification(page, 'Saved');
        // ...but the tags make it again from the name whenever they are saved (see the test.fixme below)
        expect(tagNamed(`${name} edited`)).toEqual({ name: { en: `${name} edited` }, slug: { en: `${slug}-edited` }, type: 'territories' });

        await rowAction(page, `${name} edited`, 'Delete');
        await expect(modal(page)).toContainText('No territory or zone uses this tag.');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');

        await expect(row(page, `${name} edited`)).toHaveCount(0);
        expect(tagNamed(`${name} edited`)).toBeNull();
    });

    test('refuses a slug that is not made of letters, numbers and dashes', async ({ adminPage: page }) => {
        const name = uniqueTag('Bad slug');

        await visit(page, '/app/tags');
        await page.getByRole('button', { name: 'New Tag' }).click();
        await field(modal(page), 'Name').fill(name);
        await field(modal(page), 'Name').blur();
        await expect(field(modal(page), 'Slug')).not.toHaveValue('');
        await field(modal(page), 'Slug').fill('not a slug!');
        await confirmModal(page, 'Create');

        await expect(modal(page)).toContainText('The slug field must only contain letters, numbers, dashes, and underscores.');
        expect(tagNamed(name)).toBeNull();
    });

    // Bug: the Slug field of the form is never saved. `Spatie\Tags\HasSlug` makes the slug again from the name on every
    // save (`static::saving()`), so a slug typed by hand, on a new tag or when editing one, is silently replaced.
    test.fixme('keeps the slug typed by hand', async ({ adminPage: page }) => {
        const name = uniqueTag('Custom slug');

        await visit(page, '/app/tags');
        await page.getByRole('button', { name: 'New Tag' }).click();
        await field(modal(page), 'Name').fill(name);
        await field(modal(page), 'Name').blur();
        await expect(field(modal(page), 'Slug')).not.toHaveValue('');
        await field(modal(page), 'Slug').fill('my-custom-slug');
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        expect(tagNamed(name)?.slug).toEqual({ en: 'my-custom-slug' });
    });

    test('stores the tags created in another interface language in the tags locale', async ({ browser }) => {
        const name = uniqueTag('Condominio');
        // The language preferred by the browser, which the application follows when none was chosen
        const context = await browser.newContext({ baseURL: appURL, storageState: storageStateOf('admin'), locale: 'it-IT' });
        const page = await context.newPage();

        try {
            await visit(page, '/app/tags');
            await page.getByRole('button', { name: /^Nuovo/ }).click();
            await field(modal(page), 'Nome').fill(name);
            await field(modal(page), 'Nome').blur();
            await expect(field(modal(page), 'Slug')).toHaveValue(name.replace(/ /g, '-'));
            await chooseOption(modal(page), 'Tipo', 'Territori');
            await confirmModal(page, 'Salva');
            await expectNotification(page, 'Creato');
        } finally {
            await context.close();
        }

        expect(tagNamed(name)).toEqual({ name: { en: name }, slug: { en: name.replace(/ /g, '-') }, type: 'territories' });
    });

    test('filters the tags by type', async ({ adminPage: page }) => {
        const prefix = uniqueTag('Typed');
        const [territoryTag, zoneTag] = [`${prefix} territory`, `${prefix} zone`];
        createTag(territoryTag, 'territories');
        createTag(zoneTag, 'zones');
        const shown = page.locator('.fi-ta-row').filter({ hasText: prefix });

        await visit(page, '/app/tags');
        await searchTableUntilApplied(page, prefix);
        await expect(shown).toHaveCount(2);

        await applyTableFilters(page, (panel) => chooseOption(panel, 'Filter by Type', 'Zones'));
        await expect(shown).toHaveCount(1);
        await expect(row(page, zoneTag)).toContainText('Zones');

        await applyTableFilters(page, (panel) => chooseOption(panel, 'Filter by Type', 'Territories'));
        await expect(shown).toHaveCount(1);
        await expect(row(page, territoryTag)).toContainText('Territories');
    });

    test('warns how many territories use a tag before deleting it, keeping the territories', async ({ adminPage: page }) => {
        const name = uniqueTag('Used tag');
        const kept = uniqueTag('Kept tag');
        const territoryName = uniqueName('Tagged territory');
        const territoryId = createTerritory(territoryName, [name, kept]);

        await visit(page, '/app/tags');
        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText('1');

        await rowAction(page, name, 'Delete');
        await expect(modal(page)).toContainText('This tag is used by 1 territory and 0 zones: deleting it removes it from them.');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');

        await expect(row(page, name)).toHaveCount(0);
        expect(tagNamed(name)).toBeNull();
        expect(php(`return \\App\\Models\\Territory::find(${territoryId})?->tags->pluck('name')->all();`)).toEqual([kept]);
    });

    test('filters the territories by a territory tag', async ({ adminPage: page }) => {
        const name = uniqueTag('Filter tag');
        const prefix = uniqueName('Tag filter territory');
        const [tagged, untagged] = [`${prefix} tagged`, `${prefix} untagged`];
        createTerritory(tagged, [name]);
        createTerritory(untagged, []);
        const shown = page.locator('.fi-ta-row').filter({ hasText: prefix });

        await visit(page, '/app/territories');
        await searchTableUntilApplied(page, prefix);
        await expect(shown).toHaveCount(2);

        // Through the URL: the options of the filter may not show the tag (see the test.fixme below)
        await visit(page, `/app/territories?filters[tags][values][0]=${encodeURIComponent(name)}`);
        await expect(page.getByText(`Tags: ${name}`)).toBeVisible();
        await searchTableUntilApplied(page, prefix);
        await expect(shown).toHaveCount(1);
        await expect(row(page, tagged)).toBeVisible();
    });

    // Bug: the Tags filter of the territories (`TerritoryResource`, a multiple select without search) shows only the
    // first 50 tags: once there are more, the newest ones can't be chosen at all.
    test.fixme('offers every territory tag in the filter of the territories', async ({ adminPage: page }) => {
        const name = uniqueTag('Filter tag');
        const prefix = uniqueName('Tag filter territory');
        // More tags than the 50 options shown (`SelectFilter::$optionsLimit`), before the one looked for
        php(`foreach (range(1, 60) as $number) { \\App\\Models\\Tag::findOrCreate(${JSON.stringify(name)}." other {$number}", 'territories'); } return true;`);
        createTerritory(`${prefix} tagged`, [name]);

        await visit(page, '/app/territories');
        await applyTableFilters(page, (panel) => chooseOption(panel, 'Tags', name));
        await searchTableUntilApplied(page, prefix);
        await expect(row(page, `${prefix} tagged`)).toBeVisible();
    });
});
