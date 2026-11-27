import { readFileSync, writeFileSync } from 'node:fs';
import type { Locator, Page } from '@playwright/test';
import { php } from './support/artisan';
import {
    applyTableFilters,
    bulkMenuAction,
    chooseOption,
    confirmModal,
    downloadExport,
    field,
    labelled,
    modal,
    row,
    rowAction,
    rowMenuAction,
    searchTableUntilApplied,
    settle,
    uploadImport,
    visit,
} from './support/filament';
import { expect, test, uniqueName } from './support/fixtures';

/**
 * The publications department (`app/Filament/Resources/Publications`, `PublicationRequests`, `PublicationSubscribers`):
 * the publication list, the requests and the regular publications.
 *
 * Every test works on publications, publishers and requests of its own, so that the tests running in parallel never
 * see each other's: the seeded ones are only read.
 */

type Publisher = { id: number; name: string };

type PublicationAttributes = { name: string; code?: string; category?: string; renewal_months?: number | null; is_active?: boolean };

type RequestAttributes = { user_id: number; publication_id: number; quantity?: number; requested_at?: string; ordered_at?: string | null; delivered_at?: string | null };

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
 * A code nobody else uses, for a publication.
 */
const uniqueCode = (prefix: string): string => uniqueName(prefix).replace(' ', '-').toLowerCase();

/**
 * Create a publisher of their own (with a unique first name), returning their id and full name.
 */
function createPublisher(prefix: string = 'Pubtest'): Publisher {
    return php<Publisher>(`
        $user = \\App\\Models\\User::factory()->create(['first_name' => ${JSON.stringify(uniqueName(prefix))}, 'last_name' => 'Etoe']);
        $user->assignRole('publisher');

        return ['id' => $user->id, 'name' => $user->getFullName()];
    `);
}

/**
 * Create a publication (a book, offered, by default), returning its id.
 */
function createPublication(attributes: PublicationAttributes): number {
    const id = php<number>(`
        $attributes = json_decode(${JSON.stringify(JSON.stringify({ code: uniqueCode('pub'), ...attributes }))}, true);

        return \\App\\Models\\Publication::factory()->create([
            'name' => ['en' => $attributes['name']],
            'code' => $attributes['code'],
            'category' => $attributes['category'] ?? 'books',
            'renewal_months' => $attributes['renewal_months'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ])->id;
    `);
    // With its requests and the copies received
    cleanUp(`\\App\\Models\\Publication::find(${id})?->delete();`);

    return id;
}

/**
 * Create a request of a publication (requested today, by default), returning its id.
 */
function createRequest(attributes: RequestAttributes): number {
    return php<number>(`
        $attributes = json_decode(${JSON.stringify(JSON.stringify(attributes))}, true);

        return \\App\\Models\\PublicationRequest::factory()->create([
            'quantity' => 1,
            'requested_at' => today()->toDateString(),
            ...$attributes,
        ])->id;
    `);
}

/**
 * The id of a seeded publication, by its code.
 */
const publicationIdOf = (code: string): number =>
    php<number>(`return \\App\\Models\\Publication::where('code', ${JSON.stringify(code)})->value('id');`);

/**
 * Pick an option of a searchable select that has no label of its own (e.g. in the rows of a repeater).
 */
async function pickOption(page: Page, trigger: Locator, option: string): Promise<void> {
    await trigger.click();
    const search = page.locator('.fi-select-input-search-ctn input').filter({ visible: true }).first();

    if (await search.count()) {
        await search.fill(option);
    }

    await page.getByRole('option', { name: option }).filter({ visible: true }).first().click();
}

/**
 * Choose a publisher in a select: the options are searched by first or last name, not by full name.
 */
async function choosePublisher(scope: Locator, label: string, publisher: Publisher): Promise<void> {
    const page = scope.page();
    await scope.getByRole('button', { name: labelled(label) }).or(scope.getByRole('combobox', { name: labelled(label) })).first().click();
    await page.locator('.fi-select-input-search-ctn input').filter({ visible: true }).first().fill(publisher.name.split(' ').slice(0, 2).join(' '));
    await page.getByRole('listbox').filter({ visible: true }).getByRole('option', { name: publisher.name, exact: true }).first().click();
}

/**
 * The requests page on a tab, showing only the requests of a publisher.
 */
const requestsOf = (publisher: Publisher, tab: 'to_order' | 'ordered' | 'delivered' | 'all'): string =>
    `/app/publication-requests?tab=${tab}&filters[user][value]=${publisher.id}`;

/**
 * Expect a notification (toast) with the text, waiting longer than `expect()` does: saving can be slow while the other
 * tests run.
 */
const expectNotification = (page: Page, text: string | RegExp): Promise<void> =>
    expect(page.locator('.fi-no-notification').filter({ hasText: text }).first()).toBeVisible({ timeout: 30_000 });

test.describe('Publication list', () => {
    test('creates a publication, edits it into a regular one and deletes it', async ({ adminPage: page }) => {
        const name = uniqueName('Brochure');
        const code = uniqueCode('br');
        cleanUp(`\\App\\Models\\Publication::where('code', ${JSON.stringify(code)})->get()->each->delete();`);

        await visit(page, '/app/publications');
        await page.getByRole('button', { name: 'New Publication' }).click();
        await field(modal(page), 'English').fill(name);
        await chooseOption(modal(page), 'Category', 'Brochures and Booklets');
        await field(modal(page), 'Code').fill(code);
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText(code);
        await expect(row(page, name)).toContainText('Once');
        expect(php(`return \\App\\Models\\Publication::where('code', ${JSON.stringify(code)})->first()?->only(['category', 'renewal_months', 'is_active']);`))
            .toEqual({ category: 'brochures_and_booklets', renewal_months: null, is_active: true });

        await rowMenuAction(page, name, 'Edit');
        await field(modal(page), 'Italiano').fill(`${name} IT`);
        await modal(page).getByRole('switch', { name: 'Received regularly' }).click();
        await field(modal(page), 'Renews every').fill('2');
        await confirmModal(page, 'Save changes');
        await expectNotification(page, 'Saved');

        await expect(row(page, name)).toContainText('every 2 months');
        expect(php(`$publication = \\App\\Models\\Publication::where('code', ${JSON.stringify(code)})->first(); return [$publication->renewal_months, $publication->getTranslation('name', 'it', false)];`))
            .toEqual([2, `${name} IT`]);

        await rowMenuAction(page, name, 'Delete');
        await expect(modal(page)).toContainText('The copies received regularly and the requests of this publication will be deleted too');
        await confirmModal(page, 'Delete');
        await expectNotification(page, 'Deleted');

        await expect(row(page, name)).toHaveCount(0);
        expect(php(`return \\App\\Models\\Publication::where('code', ${JSON.stringify(code)})->exists();`)).toBe(false);
    });

    test('filters the publications by category', async ({ adminPage: page }) => {
        const prefix = uniqueName('Catfilter');
        const tract = `${prefix} tract`;
        const book = `${prefix} book`;
        createPublication({ name: tract, category: 'tracts' });
        createPublication({ name: book, category: 'books' });

        await visit(page, '/app/publications');
        await searchTableUntilApplied(page, prefix);
        await expect(row(page, tract)).toBeVisible();
        await expect(row(page, book)).toBeVisible();

        await applyTableFilters(page, (panel) => chooseOption(panel, 'Category', 'Tracts'));

        await expect(row(page, tract)).toBeVisible();
        await expect(row(page, book)).toHaveCount(0);
        await expect(page.getByRole('heading', { name: 'Tracts', level: 2 })).toBeVisible();
    });

    test('opens the requests of a publication', async ({ adminPage: page }) => {
        const name = uniqueName('Requested book');
        const publicationId = createPublication({ name });
        const publisher = createPublisher();
        createRequest({ user_id: publisher.id, publication_id: publicationId, quantity: 4 });

        await visit(page, '/app/publications');
        await searchTableUntilApplied(page, name);
        await expect(row(page, name)).toContainText('1');
        await rowAction(page, name, 'Requests');

        await expect(page).toHaveURL(new RegExp(`/app/publication-requests\\?.*tab=all.*publication.*=${publicationId}`));
        await expect(page.getByRole('heading', { name: 'Requests', level: 1 })).toBeVisible();
        await expect(row(page, publisher.name)).toContainText(name);
        await expect(row(page, publisher.name)).toContainText('4');
        await expect(page.locator('.fi-ta-row').filter({ has: page.getByRole('checkbox') })).toHaveCount(1);
    });

    test('stops offering the selected publications, then offers them again', async ({ adminPage: page }) => {
        const prefix = uniqueName('Offer');
        const names = [`${prefix} one`, `${prefix} two`];
        const ids = names.map((name) => createPublication({ name }));
        const offered = (): boolean[] => php<boolean[]>(`return \\App\\Models\\Publication::whereIn('id', ${JSON.stringify(ids)})->orderBy('id')->pluck('is_active')->all();`);

        await visit(page, '/app/publications');
        await searchTableUntilApplied(page, prefix);

        await bulkMenuAction(page, names, 'Stop offering');
        await expect(modal(page)).toContainText('it can no longer be chosen in the regular publications');
        await confirmModal(page, 'Confirm');
        await expectNotification(page, 'No longer offered');
        await expect(row(page, names[0]).getByRole('switch', { name: 'Offered' })).not.toBeChecked();
        await expect(row(page, names[1]).getByRole('switch', { name: 'Offered' })).not.toBeChecked();
        expect(offered()).toEqual([false, false]);

        await bulkMenuAction(page, names, 'Offer');
        await confirmModal(page, 'Confirm');
        await expectNotification(page, 'Offered');
        await expect(row(page, names[0]).getByRole('switch', { name: 'Offered' })).toBeChecked();
        expect(offered()).toEqual([true, true]);
    });

    test('exports the publication list as a CSV file', async ({ adminPage: page }) => {
        const name = uniqueName('Exported book');
        const code = uniqueCode('exp');
        createPublication({ name, code });

        await visit(page, '/app/publications');
        const { fileName, lines: [header, ...lines] } = await downloadExport(page, 'Export Publications');

        expect(fileName).toMatch(/^export-\d+-publications\.csv$/);
        expect(header).toBe('"Name (English)",Code,Category,"Renews every (months)",Offered,"Name (Italiano)","Name (Español)"');
        expect(lines.find((line) => line.includes(`,${code},`))).toMatch(new RegExp(`^"${name}",${code},[^,]+,,yes,`));
        expect(lines).toContainEqual(expect.stringMatching(/^"The Watchtower",w,/));
    });

    test('imports publications from a CSV file', async ({ adminPage: page }, testInfo) => {
        const suffix = uniqueCode('i');
        cleanUp(`\\App\\Models\\Publication::where('code', 'like', ${JSON.stringify(`imp-%-${suffix}`)})->get()->each->delete();`);
        const file = testInfo.outputPath('publications.csv');
        writeFileSync(file, readFileSync('tests/e2e/fixtures/publications-import.csv', 'utf8').replaceAll('{suffix}', suffix));

        await visit(page, '/app/publications');
        await uploadImport(page, 'Import Publications', file);

        await expect.poll(() => php(`
            return \\App\\Models\\Publication::where('code', 'like', ${JSON.stringify(`imp-%-${suffix}`)})->orderBy('code')->get()
                ->map(fn ($publication) => [
                    'code' => $publication->code,
                    'category' => $publication->category->value,
                    'renewal_months' => $publication->renewal_months,
                    'is_active' => $publication->is_active,
                    'name' => $publication->getTranslations('name'),
                ])->all();
        `)).toEqual([
            { code: `imp-book-${suffix}`, category: 'books', renewal_months: null, is_active: true, name: { en: `Imported Book ${suffix}`, it: `Libro importato ${suffix}` } },
            { code: `imp-monthly-${suffix}`, category: 'watchtower', renewal_months: 1, is_active: false, name: { en: `Imported Monthly ${suffix}`, es: `Mensual importado ${suffix}` } },
        ]);

        await visit(page, '/app/publications');
        await searchTableUntilApplied(page, suffix);
        await expect(row(page, `Imported Book ${suffix}`)).toBeVisible();
        await expect(row(page, `Imported Monthly ${suffix}`)).toContainText('every month');
    });
});

test.describe('Publication requests', () => {
    test('a new request moves from "to order" to "ordered" to "delivered"', async ({ adminPage: page }) => {
        const publisher = createPublisher();
        const publication = uniqueName('Ordered book');
        createPublication({ name: publication });

        await visit(page, '/app/publication-requests');
        await page.getByRole('button', { name: 'New request' }).click();
        await choosePublisher(modal(page), 'Publisher', publisher);
        await chooseOption(modal(page), 'Publication', publication);
        await field(modal(page), 'Copies').fill('3');
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        const statusOf = (): Record<string, unknown> | null => php(`return \\App\\Models\\PublicationRequest::where('user_id', ${publisher.id})->first()?->only(['quantity', 'ordered_at', 'delivered_at']);`);
        expect(statusOf()).toEqual({ quantity: 3, ordered_at: null, delivered_at: null });

        await visit(page, requestsOf(publisher, 'to_order'));
        await expect(row(page, publication)).toContainText('Requested');
        await rowAction(page, publication, 'Ordered');
        await expect(field(modal(page), 'Ordered on')).toHaveValue(/^\d{4}-\d{2}-\d{2}$/);
        await confirmModal(page, 'Submit');
        await expectNotification(page, 'Marked as ordered');
        await expect(row(page, publication)).toHaveCount(0);

        await visit(page, requestsOf(publisher, 'ordered'));
        await expect(row(page, publication)).toContainText('Ordered');
        expect(statusOf()?.ordered_at).not.toBeNull();
        await rowAction(page, publication, 'Delivered');
        await confirmModal(page, 'Submit');
        await expectNotification(page, 'Marked as delivered');
        await expect(row(page, publication)).toHaveCount(0);

        await visit(page, requestsOf(publisher, 'delivered'));
        await expect(row(page, publication)).toContainText('Delivered');
        expect(statusOf()?.delivered_at).not.toBeNull();

        await visit(page, requestsOf(publisher, 'all'));
        await expect(row(page, publication)).toContainText('Delivered');
    });

    test('marks the selected requests as ordered, then as delivered', async ({ adminPage: page }) => {
        const publisher = createPublisher();
        const prefix = uniqueName('Bulk book');
        const publications = [`${prefix} A`, `${prefix} B`];
        const ids = publications.map((name) => createRequest({ user_id: publisher.id, publication_id: createPublication({ name }) }));
        const steps = (): { ordered: boolean; delivered: boolean }[] => php(`
            return \\App\\Models\\PublicationRequest::whereIn('id', ${JSON.stringify(ids)})->orderBy('id')->get()
                ->map(fn ($request) => ['ordered' => $request->ordered_at !== null, 'delivered' => $request->delivered_at !== null])->all();
        `);

        await visit(page, requestsOf(publisher, 'to_order'));
        await bulkMenuAction(page, publications, 'Ordered');
        await confirmModal(page, 'Submit');
        await expectNotification(page, 'Marked as ordered');
        await expect(page.getByText('No requests')).toBeVisible();
        expect(steps()).toEqual([{ ordered: true, delivered: false }, { ordered: true, delivered: false }]);

        await visit(page, requestsOf(publisher, 'ordered'));
        await bulkMenuAction(page, publications, 'Delivered');
        await confirmModal(page, 'Submit');
        await expectNotification(page, 'Marked as delivered');
        await expect(page.getByText('No requests')).toBeVisible();
        expect(steps()).toEqual([{ ordered: true, delivered: true }, { ordered: true, delivered: true }]);

        await visit(page, requestsOf(publisher, 'delivered'));
        await expect(row(page, publications[0])).toContainText('Delivered');
        await expect(row(page, publications[1])).toContainText('Delivered');
    });

    test('filters the requests by publisher, publication and date', async ({ adminPage: page }) => {
        const [first, second] = [createPublisher(), createPublisher()];
        const prefix = uniqueName('Filtered book');
        const [bookA, bookB] = [`${prefix} A`, `${prefix} B`];
        const [bookAId, bookBId] = [createPublication({ name: bookA }), createPublication({ name: bookB })];
        createRequest({ user_id: first.id, publication_id: bookAId, requested_at: '2025-03-15' });
        createRequest({ user_id: first.id, publication_id: bookBId });
        createRequest({ user_id: second.id, publication_id: bookAId });

        await visit(page, '/app/publication-requests?tab=all');
        await searchTableUntilApplied(page, prefix);
        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(3);

        await applyTableFilters(page, (panel) => choosePublisher(panel, 'Publisher', first));
        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(2);
        await expect(row(page, second.name)).toHaveCount(0);

        await applyTableFilters(page, async (panel) => {
            await field(panel, 'Requested from').fill('2025-03-01');
            await field(panel, 'Requested until').fill('2025-03-31');
        });
        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(1);
        await expect(row(page, prefix)).toContainText(bookA);
        await expect(row(page, prefix)).toContainText('15 Mar 2025');

        await page.getByRole('button', { name: 'Filter', exact: true }).click();
        await page.locator('.fi-dropdown-panel').filter({ visible: true }).getByRole('button', { name: 'Reset' }).click();
        await settle(page);
        await page.keyboard.press('Escape');
        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(3);

        await applyTableFilters(page, (panel) => chooseOption(panel, 'Publication', bookB));
        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(1);
        await expect(row(page, prefix)).toContainText(first.name);
        await expect(row(page, prefix)).toContainText(bookB);
    });

    test('shows the totals and the chart of the requests @mobile', async ({ adminPage: page }) => {
        const toOrder = php<number>('return \\App\\Models\\PublicationRequest::query()->toOrder()->count();');

        await visit(page, '/app/publication-requests');

        const stats = page.locator('.fi-wi-stats-overview-stat');
        await expect(stats).toHaveCount(3);
        await expect(stats.nth(0)).toContainText('To order or retrieve');
        // Other tests may add requests meanwhile: at least the ones counted before opening the page
        expect(Number((await stats.nth(0).locator('.fi-wi-stats-overview-stat-value').textContent())?.trim())).toBeGreaterThanOrEqual(toOrder);
        await expect(stats.nth(0)).toContainText(/\d+ cop(y|ies)/);
        await expect(stats.nth(1)).toContainText('Ordered');
        await expect(stats.nth(2)).toContainText('Delivered this month');
        await expect(page.getByRole('heading', { name: 'Requests over time' })).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Filter chart data' })).toHaveValue('all');
        await expect(page.locator('.fi-wi-chart canvas')).toBeVisible();
    });
});

test.describe('Regular publications', () => {
    test('adds a publisher receiving copies, reflected by the totals', async ({ adminPage: page }) => {
        const publisher = createPublisher('Subscriber');
        const watchtowerId = publicationIdOf('w');

        await visit(page, '/app/publication-subscribers');
        await page.getByRole('button', { name: 'Add publisher' }).click();
        await choosePublisher(modal(page), 'Publisher', publisher);
        await modal(page).getByRole('button', { name: 'Add publication' }).click();
        const item = modal(page).getByRole('group', { name: 'Copies received' }).getByRole('row').filter({ has: page.getByRole('spinbutton') });
        await pickOption(page, item.locator('.fi-select-input-btn'), 'The Watchtower');
        await item.getByRole('spinbutton').fill('3');
        await confirmModal(page, 'Create');
        await expectNotification(page, 'Created');

        expect(php(`return \\App\\Models\\PublicationSubscriber::where('user_id', ${publisher.id})->sole()->activeSubscriptions()->get(['publication_id', 'quantity'])->toArray();`))
            .toEqual([{ publication_id: watchtowerId, quantity: 3 }]);

        // The totals count the publishers shown by the table: only this one, once searched
        await searchTableUntilApplied(page, publisher.name.split(' ').slice(0, 2).join(' '));
        await expect(page.locator('.fi-ta-row').filter({ has: page.getByRole('checkbox') })).toHaveCount(1);
        await expect(row(page, publisher.name)).toContainText('3');
        const stats = page.locator('.fi-wi-stats-overview-stat');
        await expect(stats).toHaveCount(1);
        await expect(stats.first()).toContainText('The Watchtower');
        await expect(stats.first().locator('.fi-wi-stats-overview-stat-value')).toHaveText('3');
        await expect(stats.first()).toContainText('1 publisher');
    });

    test('opens the requests of a publisher', async ({ adminPage: page }) => {
        const publisher = createPublisher('Subscriber');
        const watchtowerId = publicationIdOf('w');
        php(`\\App\\Models\\PublicationSubscriber::factory()->receiving(\\App\\Models\\Publication::find(${watchtowerId}), 2)->create(['user_id' => ${publisher.id}]); return true;`);
        const book = uniqueName('Subscriber book');
        createRequest({ user_id: publisher.id, publication_id: createPublication({ name: book }), quantity: 2 });

        await visit(page, '/app/publication-subscribers');
        await searchTableUntilApplied(page, publisher.name.split(' ').slice(0, 2).join(' '));
        await rowAction(page, publisher.name, 'Requests');

        await expect(page).toHaveURL(new RegExp(`/app/publication-requests\\?.*tab=all.*user.*=${publisher.id}`));
        await expect(row(page, book)).toContainText(publisher.name);
    });

    test('removes a publisher from the list, keeping their history', async ({ adminPage: page }) => {
        const publisher = createPublisher('Subscriber');
        const watchtowerId = publicationIdOf('w');
        php(`\\App\\Models\\PublicationSubscriber::factory()->receiving(\\App\\Models\\Publication::find(${watchtowerId}), 2)->create(['user_id' => ${publisher.id}]); return true;`);

        await visit(page, '/app/publication-subscribers');
        await searchTableUntilApplied(page, publisher.name.split(' ').slice(0, 2).join(' '));
        await rowAction(page, publisher.name, 'Remove');
        await expect(modal(page).getByRole('heading', { name: `Remove ${publisher.name} from the list` })).toBeVisible();
        await confirmModal(page, 'Confirm');
        await expectNotification(page, 'Removed from the list');

        await expect(row(page, publisher.name)).toHaveCount(0);
        expect(php(`
            $subscriber = \\App\\Models\\PublicationSubscriber::where('user_id', ${publisher.id})->sole();

            return [$subscriber->activeSubscriptions()->count(), $subscriber->subscriptions()->count()];
        `)).toEqual([0, 1]);
    });

    test('removes the selected publishers from the list', async ({ adminPage: page }) => {
        const prefix = uniqueName('Bulksub');
        const publishers = [createPublisher(`${prefix} one`), createPublisher(`${prefix} two`)];
        const watchtowerId = publicationIdOf('w');

        for (const publisher of publishers) {
            php(`\\App\\Models\\PublicationSubscriber::factory()->receiving(\\App\\Models\\Publication::find(${watchtowerId}), 1)->create(['user_id' => ${publisher.id}]); return true;`);
        }

        await visit(page, '/app/publication-subscribers');
        await searchTableUntilApplied(page, prefix);
        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(2);
        await expect(page.locator('.fi-wi-stats-overview-stat-value').first()).toHaveText('2');

        await bulkMenuAction(page, publishers.map((publisher) => publisher.name), 'Remove');
        await confirmModal(page, 'Confirm');
        await expectNotification(page, 'Removed from the list');

        await expect(page.locator('.fi-ta-row').filter({ hasText: prefix })).toHaveCount(0);
        expect(php(`return \\App\\Models\\PublicationSubscription::query()->active()->whereHas('subscriber', fn ($query) => $query->whereIn('user_id', ${JSON.stringify(publishers.map((publisher) => publisher.id))}))->count();`)).toBe(0);
    });
});
