<?php

use App\Filament\Resources\Territories\TerritoryResource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

beforeEach(function () {
    // Render the real assets when they are built (as in CI), so that documentation images missing
    // from the Vite build are caught, and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

/**
 * Get the Markdown files of the user documentation for the given locale.
 *
 * @return array<string, array{type: string, title: ?string, content: string}> keyed by page slug (e.g. `territories/zones`)
 */
function userDocumentationFiles(string $locale): array
{
    return collect(File::allFiles(base_path("docs/user-documentation/{$locale}")))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'md')
        ->mapWithKeys(function (SplFileInfo $file): array {
            $content = $file->getContents();
            $frontMatter = Str::between($content, "---\n", "\n---");

            return [
                Str::beforeLast(str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()), '.md') => [
                    'type' => Str::match('/^type:\s*(\S+)/m', $frontMatter) ?: 'documentation',
                    'title' => Str::match('/^title:\s*(.+)$/m', $frontMatter) ?: null,
                    'content' => $content,
                ],
            ];
        })
        ->sortKeys()
        ->all();
}

dataset('documentation locales', ['en', 'it']);

test('every documentation page is rendered in the knowledge base', function (string $locale) {
    app()->setLocale($locale);

    foreach (userDocumentationFiles($locale) as $slug => $page) {
        // Groups are only used to build the navigation and have no page
        if ($page['type'] === 'group') {
            continue;
        }

        $response = $this->get("/knowledge-base/{$slug}?locale={$locale}");

        expect($response->getStatusCode())->toBe(200, "The documentation page [{$locale}/{$slug}] is not rendered.");
        $response->assertSeeText($page['title']);
    }
})->with('documentation locales');

test('documentation groups have a translated title', function (string $locale) {
    $groups = collect(userDocumentationFiles($locale))->where('type', 'group');

    expect($groups)->not->toBeEmpty();

    foreach ($groups as $slug => $group) {
        expect($group['title'])->not->toBeNull("The documentation group [{$locale}/{$slug}] has no title.");
        expect(File::isDirectory(base_path("docs/user-documentation/{$locale}/{$slug}")))
            ->toBeTrue("The documentation group [{$locale}/{$slug}] has no directory with its pages.");
    }
})->with('documentation locales');

test('every documentation page is available in every locale', function () {
    expect(array_keys(userDocumentationFiles('it')))->toBe(array_keys(userDocumentationFiles('en')));

    $images = fn (string $locale): array => collect(File::allFiles(base_path("docs/user-documentation/{$locale}")))
        ->reject(fn (SplFileInfo $file): bool => $file->getExtension() === 'md')
        ->map(fn (SplFileInfo $file): string => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();

    expect($images('it'))->toBe($images('en'));
});

test('documentation links and images point to existing files', function (string $locale) {
    foreach (userDocumentationFiles($locale) as $slug => $page) {
        preg_match_all('/(!?)\[[^\]]*\]\(([^)\s]+)\)/', $page['content'], $matches, PREG_SET_ORDER);

        foreach ($matches as [, $isImage, $url]) {
            if (Str::startsWith($url, ['http://', 'https://', '#'])) {
                continue;
            }

            if ($isImage) {
                expect($url)->toStartWith("/docs/user-documentation/{$locale}/", "The image [{$url}] in [{$locale}/{$slug}] belongs to another locale.");
                expect(File::exists(base_path(ltrim($url, '/'))))->toBeTrue("The image [{$url}] in [{$locale}/{$slug}] does not exist.");

                continue;
            }

            // Links between pages are relative to the page URL (e.g. `/knowledge-base/basics/profile`)
            $segments = [];
            foreach (explode('/', dirname($slug).'/'.Str::before($url, '#')) as $segment) {
                if ($segment === '..') {
                    array_pop($segments);
                } elseif ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }

            expect(File::exists(base_path("docs/user-documentation/{$locale}/".implode('/', $segments).'.md')))
                ->toBeTrue("The link [{$url}] in [{$locale}/{$slug}] does not point to an existing page.");
        }
    }
})->with('documentation locales');

test('documentation linked from the territories resource exists in every locale', function (string $locale) {
    foreach (TerritoryResource::getDocumentation() as $documentationId) {
        $path = str_replace('.', '/', $documentationId);

        expect(File::exists(base_path("docs/user-documentation/{$locale}/{$path}.md")))
            ->toBeTrue("The documentation page [{$documentationId}] linked from the territories resource does not exist.");
    }
})->with('documentation locales');
