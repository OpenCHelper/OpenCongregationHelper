<?php

use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
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
