<?php

use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Filament\Widgets\TerritoryGlobalMap;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
    The areas and the lines drawn on the map of a territory are shown wherever the territory is used: on the page of
    its assignments (also the shared one) and on the dashboard map.
*/

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->user = User::factory()->create();
    foreach (['ViewAny:Territory', 'View:TerritoryAssignment', 'ViewAny:TerritoryAssignment'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);
});

/**
 * A GeoJSON feature with the given geometry.
 *
 * @param  array<int, mixed>  $coordinates  [longitude, latitude] points, as GeoJSON has them
 * @return array<string, mixed>
 */
function mapFeature(string $type, array $coordinates): array
{
    return ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => $type, 'coordinates' => $coordinates]];
}

/**
 * A territory whose map has an area, a street to work and the point of an old text label.
 */
function territoryWithAreaAndLine(): Territory
{
    return Territory::factory()
        ->for(TerritoryZone::factory()->create(['color' => '#ff0000']), 'territoryZone')
        ->create(['geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [
            mapFeature('Polygon', [[[12.1, 41.1], [12.2, 41.1], [12.2, 41.2], [12.1, 41.1]]]),
            mapFeature('LineString', [[12.1, 41.1], [12.3, 41.3]]),
            mapFeature('Point', [12.15, 41.15]),
        ]])]);
}

test('the shapes of a map are read with latitude and longitude swapped, points left out', function () {
    $territory = new Territory(['geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [
        mapFeature('Polygon', [[[12.1, 41.1], [12.2, 41.1], [12.2, 41.2], [12.1, 41.1]]]),
        mapFeature('MultiPolygon', [[[[13.1, 42.1], [13.2, 42.1], [13.1, 42.1]]], [[[14.1, 43.1], [14.2, 43.1], [14.1, 43.1]]]]),
        mapFeature('LineString', [[12.1, 41.1], [12.3, 41.3]]),
        mapFeature('MultiLineString', [[[15.1, 44.1], [15.2, 44.2]], [[16.1, 45.1], [16.2, 45.2]]]),
        mapFeature('Point', [12.15, 41.15]),
    ]])]);

    expect($territory->getMapShapes())->toBe([
        'areas' => [
            [[[41.1, 12.1], [41.1, 12.2], [41.2, 12.2], [41.1, 12.1]]],
            [[[42.1, 13.1], [42.1, 13.2], [42.1, 13.1]]],
            [[[43.1, 14.1], [43.1, 14.2], [43.1, 14.1]]],
        ],
        'lines' => [
            [[41.1, 12.1], [41.3, 12.3]],
            [[44.1, 15.1], [44.2, 15.2]],
            [[45.1, 16.1], [45.2, 16.2]],
        ],
    ]);
});

test('a territory without a map has no shapes', function () {
    expect((new Territory)->getMapShapes())->toBe(['areas' => [], 'lines' => []]);
});

test('the dashboard map draws the areas and the lines of the territories', function () {
    territoryWithAreaAndLine();

    $layers = collect(Livewire::test(TerritoryGlobalMap::class)->instance()->getMapData()['layersData']);

    expect($layers->pluck('type')->all())->toBe(['polygon', 'polyline'])
        ->and($layers->last()['points'])->toBe([[41.1, 12.1], [41.3, 12.3]]);
});

test('the page of an assignment draws the areas and the lines of its territory', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => territoryWithAreaAndLine()->id,
        'assignee_id' => $this->user->id,
        'ended_at' => null,
    ]);

    // The layers of the map are in the JSON of the page, each with an id made of its type and a hash
    $html = Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->id])->html();

    expect(preg_match_all('/polygon-[0-9a-f]{32}/', $html))->toBe(1)
        ->and(preg_match_all('/polyline-[0-9a-f]{32}/', $html))->toBe(1);
});
