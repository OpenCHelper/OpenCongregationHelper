<?php

use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Dotswan\MapPicker\Fields\Map;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach (['ViewAny:Territory', 'View:Territory', 'Create:Territory', 'Update:Territory'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);
});

/**
 * A GeoJSON feature collection with a single polygon.
 *
 * The coordinates are literals: computed ones (e.g. `12.37 + 0.1`) can carry floating point
 * errors that a MySQL JSON column rounds away, so they would not round-trip.
 *
 * @param  list<array{float, float}>  $ring
 */
function territoryPolygon(array $ring = [[12.39, 41.99], [12.37, 41.87], [12.45, 41.90], [12.39, 41.99]]): string
{
    return json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => [],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [$ring],
            ],
        ]],
    ]);
}

test('saving a territory keeps its map when the map only reports its centre', function () {
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'geojson' => territoryPolygon(),
        'image_path' => null,
    ]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        // What the map picker sends when it loads or moves: its centre, without the drawn shapes
        ->set('data.location', ['lat' => 41.9, 'lng' => 12.5])
        ->assertSet('data.location.geojson', json_decode(territoryPolygon()))
        ->fillForm(['notes' => 'Only the notes changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $territory->refresh();

    expect($territory->notes)->toBe('Only the notes changed')
        ->and(json_decode($territory->geojson, true))->toEqual(json_decode(territoryPolygon(), true));
});

test('saving a territory stores the shapes drawn on the map', function () {
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'geojson' => territoryPolygon(),
        'image_path' => null,
    ]);

    $redrawnPolygon = territoryPolygon([[12.49, 41.99], [12.47, 41.87], [12.55, 41.90], [12.49, 41.99]]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->set('data.location', ['lat' => 41.9, 'lng' => 12.5, 'geojson' => json_decode($redrawnPolygon, true)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(json_decode($territory->refresh()->geojson, true))->toEqual(json_decode($redrawnPolygon, true));
});

test('removing every shape from the map clears the territory map', function () {
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'geojson' => territoryPolygon(),
        'image_path' => null,
    ]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->set('data.location', ['lat' => 41.9, 'lng' => 12.5, 'geojson' => ['type' => 'FeatureCollection', 'features' => []]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($territory->refresh()->geojson)->toBeNull();
});

test('a territory can be created', function () {
    Livewire::test(CreateTerritory::class)
        ->fillForm([
            'name' => 'Via Roma',
            'territory_zone_id' => $this->zone->getKey(),
            'notes' => 'Ring the bell twice',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Territory::class, [
        'name' => 'Via Roma',
        'territory_zone_id' => $this->zone->getKey(),
        'notes' => 'Ring the bell twice',
    ]);
});

test('an image uploaded while creating a territory becomes its image', function () {
    Storage::fake('local');

    Livewire::test(CreateTerritory::class)
        ->fillForm([
            'name' => 'Via Roma',
            'territory_zone_id' => $this->zone->getKey(),
            'image_file' => UploadedFile::fake()->image('via-roma.png'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $territory = Territory::query()->where('name', 'Via Roma')->firstOrFail();

    expect($territory->image_path)->toStartWith('territories/');
    Storage::disk('local')->assertExists($territory->image_path);
});

test('the territory form loads the script that shows the map as soon as its tab is opened', function () {
    $this->withoutVite();

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'geojson' => null,
        'image_path' => 'https://example.com/territory.png',
    ]);

    // The script wraps the map picker before Alpine starts, so it must come before Livewire's script
    $this->get(TerritoryResource::getUrl('edit', ['record' => $territory]))
        ->assertOk()
        ->assertSeeInOrder([
            FilamentAsset::getScriptSrc('filament-map-picker-fix'),
            '/livewire.js',
        ], escape: false);
});

test('a territory cannot be created with the name of another territory of the same zone', function (bool $isOtherTerritoryDeleted) {
    $existingTerritory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma']);

    if ($isOtherTerritoryDeleted) {
        $existingTerritory->delete();
    }

    Livewire::test(CreateTerritory::class)
        ->fillForm([
            'name' => 'Via Roma',
            'territory_zone_id' => $this->zone->getKey(),
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique'])
        // The message suggests restoring the territory, which may have been deleted
        ->assertHasFormErrors(['name' => fn (array $failedRules, array $messages): bool => $messages === [__('filament-resources.territory.validation_name_unique')]]);

    expect(Territory::withTrashed()->where('name', 'Via Roma')->count())->toBe(1);
})->with([
    'existing territory' => false,
    'deleted territory' => true,
]);

test('a territory can have the name of a territory of another zone', function () {
    Territory::factory()->for(TerritoryZone::factory()->create(['name' => 'South']), 'territoryZone')->create(['name' => 'Via Roma']);

    Livewire::test(CreateTerritory::class)
        ->fillForm([
            'name' => 'Via Roma',
            'territory_zone_id' => $this->zone->getKey(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Territory::class, [
        'name' => 'Via Roma',
        'territory_zone_id' => $this->zone->getKey(),
    ]);
});

test('a territory can be saved without changing its name', function () {
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma', 'image_path' => null]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->fillForm(['notes' => 'Only the notes changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($territory->refresh()->notes)->toBe('Only the notes changed');
});

test('a territory cannot be renamed like another territory of the same zone', function () {
    Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma']);
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Milano', 'image_path' => null]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->fillForm(['name' => 'Via Roma'])
        ->call('save')
        ->assertHasFormErrors(['name' => 'unique']);

    expect($territory->refresh()->name)->toBe('Via Milano');
});

test('the territories page of a zone rejects the name of another territory of that zone', function () {
    foreach (['ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone'] as $permission) {
        auth()->user()->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma']);
    $south = TerritoryZone::factory()->create(['name' => 'South']);

    // New territories go in the zone of the page, whatever zone is selected in the form
    Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getKey()])
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'Via Roma',
            'territory_zone_id' => $south->getKey(),
        ])
        ->assertHasFormErrors(['name' => 'unique']);

    expect(Territory::query()->where('name', 'Via Roma')->count())->toBe(1);
});

test('the map of a new territory opens on the configured default centre', function () {
    config(['filament-leaflet.default_map_center' => [39.8017, -89.6436]]);

    Livewire::test(CreateTerritory::class)
        ->assertSchemaComponentExists('location', 'form', checkComponentUsing: fn (Map $map): bool => json_decode($map->getMapConfig(), true)['default'] === ['lat' => 39.8017, 'lng' => -89.6436]);
});
