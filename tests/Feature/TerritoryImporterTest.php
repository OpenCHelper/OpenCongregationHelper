<?php

use App\Filament\Imports\TerritoryImporter;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $options
 */
function makeTerritoryImporter(array $options = []): TerritoryImporter
{
    $import = Import::create([
        'file_name' => 'territories.csv',
        'file_path' => 'territories.csv',
        'importer' => TerritoryImporter::class,
        'total_rows' => 1,
        'user_id' => User::factory()->create()->id,
    ]);

    $columnMap = collect(TerritoryImporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])
        ->all();

    return new TerritoryImporter($import, $columnMap, [
        'keep_image_links' => true,
        ...$options,
    ]);
}

test('a new territory can be imported into an existing zone', function () {
    $zone = TerritoryZone::factory()->create(['name' => 'North']);

    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
    ]);

    expect(Territory::where('name', 'T-1')->where('territory_zone_id', $zone->id)->exists())->toBeTrue();
});

test('a new zone is created when importing into a missing zone', function () {
    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]);

    $zone = TerritoryZone::where('name', 'South')->sole();

    expect(Territory::where('name', 'T-1')->where('territory_zone_id', $zone->id)->exists())->toBeTrue();
});

test('importing a territory that already exists in the same zone fails', function () {
    $zone = TerritoryZone::factory()->create(['name' => 'North']);
    Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'T-1']);

    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
    ]);
})->throws(RowImportFailedException::class);

test('a territory with the same name can be imported into a different zone', function () {
    $north = TerritoryZone::factory()->create(['name' => 'North']);
    TerritoryZone::factory()->create(['name' => 'South']);
    Territory::factory()->for($north, 'territoryZone')->create(['name' => 'T-1']);

    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]);

    expect(Territory::where('name', 'T-1')->count())->toBe(2);
});

test('updating existing territories matches both the name and the zone', function () {
    $north = TerritoryZone::factory()->create(['name' => 'North']);
    $south = TerritoryZone::factory()->create(['name' => 'South']);
    $northTerritory = Territory::factory()->for($north, 'territoryZone')->create(['name' => 'T-1', 'notes' => 'north']);
    $southTerritory = Territory::factory()->for($south, 'territoryZone')->create(['name' => 'T-1', 'notes' => 'south']);

    makeTerritoryImporter(['updateExisting' => true])([
        'name' => 'T-1',
        'notes' => 'updated',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]);

    expect($southTerritory->refresh()->notes)->toBe('updated')
        ->and($northTerritory->refresh()->notes)->toBe('north')
        ->and(Territory::where('name', 'T-1')->count())->toBe(2);
});

/**
 * A valid 1x1 PNG image.
 */
function pngImage(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

/**
 * @return array<string, string>
 */
function territoryRowWithImage(string $imageUrl): array
{
    return [
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
        'image_path' => $imageUrl,
    ];
}

test('images are downloaded into the local disk with the extension of their MIME type', function () {
    Storage::fake('local');
    Http::fake(['93.184.216.34/*' => Http::response(pngImage(), 200, ['Content-Type' => 'image/png'])]);

    makeTerritoryImporter(['keep_image_links' => false])(territoryRowWithImage('https://93.184.216.34/images/territory'));

    $imagePath = Territory::where('name', 'T-1')->sole()->image_path;

    expect($imagePath)->toStartWith('territories/')->toEndWith('.png');
    Storage::disk('local')->assertExists($imagePath);
    expect(Storage::disk('local')->files('territories'))->toBe([$imagePath]);
});

test('image links are kept as they are when downloading is disabled', function () {
    Http::fake();

    makeTerritoryImporter(['keep_image_links' => true])(territoryRowWithImage('https://93.184.216.34/images/territory.png'));

    expect(Territory::where('name', 'T-1')->sole()->image_path)->toBe('https://93.184.216.34/images/territory.png');
    Http::assertNothingSent();
});

test('images on private or reserved hosts are not downloaded', function (string $imageUrl) {
    Storage::fake('local');
    Http::fake();

    expect(fn () => makeTerritoryImporter(['keep_image_links' => false])(territoryRowWithImage($imageUrl)))
        ->toThrow(RowImportFailedException::class);

    Http::assertNothingSent();
    expect(Territory::count())->toBe(0);
})->with([
    'loopback' => 'http://127.0.0.1/image.png',
    'localhost' => 'http://localhost/image.png',
    'private network' => 'http://192.168.1.10/image.png',
    'link-local (cloud metadata)' => 'http://169.254.169.254/latest/meta-data',
]);

test('invalid image downloads are rejected and deleted', function (int $status, string $body) {
    Storage::fake('local');
    Http::fake(['93.184.216.34/*' => Http::response($body, $status)]);

    expect(fn () => makeTerritoryImporter(['keep_image_links' => false])(territoryRowWithImage('https://93.184.216.34/image.png')))
        ->toThrow(RowImportFailedException::class);

    expect(Storage::disk('local')->files('territories'))->toBe([])
        ->and(Territory::count())->toBe(0);
})->with([
    'not an image' => [200, '<html><body>Not an image</body></html>'],
    'HTTP error' => [404, 'Not found'],
    'larger than 10 MB' => fn () => [200, pngImage().str_repeat('0', 10 * 1024 * 1024)],
]);
