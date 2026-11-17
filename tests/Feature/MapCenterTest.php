<?php

use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Support\MapCenter;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    User::factory()->create(['id' => 1]);

    config(['filament-leaflet.default_map_center' => [41.159063, -74.255188]]);
});

/**
 * Build the GeoJSON of a square of the given size centred on the given position.
 */
function squareGeoJson(float $latitude, float $longitude, float $size = 0.002): string
{
    $half = $size / 2;
    $ring = [
        [$longitude - $half, $latitude - $half],
        [$longitude + $half, $latitude - $half],
        [$longitude + $half, $latitude + $half],
        [$longitude - $half, $latitude + $half],
        [$longitude - $half, $latitude - $half],
    ];

    return json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => [],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
        ]],
    ]);
}

/**
 * Create a territory whose map is a small square centred on the given position.
 */
function territoryCentredOn(TerritoryZone $zone, float $latitude, float $longitude): Territory
{
    return Territory::factory()->for($zone, 'territoryZone')->create([
        'geojson' => squareGeoJson($latitude, $longitude),
    ]);
}

test('the configured default centre is used when no territory has a map', function () {
    $zone = TerritoryZone::factory()->create();
    Territory::factory()->for($zone, 'territoryZone')->create(['geojson' => null]);

    expect(MapCenter::resolve())->toBe([41.159063, -74.255188])
        ->and(MapCenter::resolve($zone->getKey()))->toBe([41.159063, -74.255188]);
});

test('the centre is the median of the centres of the territories, ignoring a far away one', function () {
    $zone = TerritoryZone::factory()->create();
    territoryCentredOn($zone, 10.0, 20.0);
    territoryCentredOn($zone, 10.2, 20.4);
    territoryCentredOn($zone, 10.4, 20.2);
    // Drawn far away by mistake: an average would be pulled towards it
    territoryCentredOn($zone, 60.0, 80.0);
    territoryCentredOn($zone, 10.6, 20.6);

    expect(MapCenter::resolve())->toEqualWithDelta([10.4, 20.4], 0.000001);
});

test('the centre of a zone is the centre of its own territories', function () {
    $north = TerritoryZone::factory()->create();
    $south = TerritoryZone::factory()->create();
    territoryCentredOn($north, 50.0, 10.0);
    territoryCentredOn($south, 40.0, 10.0);
    territoryCentredOn($south, 40.2, 10.2);
    territoryCentredOn($south, 40.4, 10.4);

    expect(MapCenter::resolve($north->getKey()))->toEqualWithDelta([50.0, 10.0], 0.000001)
        ->and(MapCenter::resolve($south->getKey()))->toEqualWithDelta([40.2, 10.2], 0.000001);
});

test('a zone without maps falls back to the centre of all the territories', function () {
    $mapped = TerritoryZone::factory()->create();
    $empty = TerritoryZone::factory()->create();
    territoryCentredOn($mapped, 45.0, 9.0);

    expect(MapCenter::resolve($empty->getKey()))->toEqualWithDelta([45.0, 9.0], 0.000001);
});

test('the cached centre follows the territories that are added, moved and deleted', function () {
    $zone = TerritoryZone::factory()->create();

    expect(MapCenter::resolve())->toBe([41.159063, -74.255188]);

    $territory = territoryCentredOn($zone, 45.0, 9.0);
    expect(MapCenter::resolve())->toEqualWithDelta([45.0, 9.0], 0.000001);

    $territory->update(['geojson' => squareGeoJson(46.0, 8.0)]);
    expect(MapCenter::resolve())->toEqualWithDelta([46.0, 8.0], 0.000001);

    $territory->delete();
    expect(MapCenter::resolve())->toBe([41.159063, -74.255188]);
});

test('the centre of a GeoJSON is the centre of the bounding box of all its geometries', function () {
    $geojson = json_encode([
        'type' => 'FeatureCollection',
        'features' => [
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [10.0, 40.0]]],
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'LineString', 'coordinates' => [[12.0, 42.0], [11.0, 41.0]]]],
        ],
    ]);

    expect(MapCenter::centerOf($geojson))->toBe([41.0, 11.0])
        ->and(MapCenter::centerOf(null))->toBeNull()
        ->and(MapCenter::centerOf('{"type":"FeatureCollection","features":[]}'))->toBeNull();
});
