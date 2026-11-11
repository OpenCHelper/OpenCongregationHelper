<?php

use App\Filament\Imports\TerritoryImporter;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;

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
