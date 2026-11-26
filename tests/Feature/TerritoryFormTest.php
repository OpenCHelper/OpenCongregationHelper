<?php

use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ViewTerritory;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Dotswan\MapPicker\Fields\Map;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs;
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

test('the image of a territory cannot be an SVG, which can carry scripts', function () {
    Storage::fake('local');

    Livewire::test(CreateTerritory::class)
        ->fillForm([
            'name' => 'Via Roma',
            'territory_zone_id' => $this->zone->getKey(),
            'image_file' => UploadedFile::fake()->createWithContent('via-roma.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])
        ->call('create')
        ->assertHasFormErrors(['image_file']);

    expect(Territory::query()->where('name', 'Via Roma')->exists())->toBeFalse();
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

test('an image uploaded from the territories page of a zone becomes the territory image', function (bool $isEditing) {
    Storage::fake('local');

    foreach (['ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone'] as $permission) {
        auth()->user()->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $page = Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getKey()]);

    if ($isEditing) {
        $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma', 'image_path' => null]);

        $page->callAction(TestAction::make('edit')->table($territory), [
            'image_file' => UploadedFile::fake()->image('via-roma.png'),
        ]);
    } else {
        $page->callAction(TestAction::make('create')->table(), [
            'name' => 'Via Roma',
            'territory_zone_id' => $this->zone->getKey(),
            'image_file' => UploadedFile::fake()->image('via-roma.png'),
        ]);
    }

    $page->assertHasNoFormErrors();

    $territory = Territory::query()->where('name', 'Via Roma')->firstOrFail();

    expect($territory->image_path)->toStartWith('territories/');
    Storage::disk('local')->assertExists($territory->image_path);
})->with([
    'create' => false,
    'edit' => true,
]);

test('a territory with a stored image can be edited', function (bool $isFromZonePage, string $imageChange) {
    Storage::fake('local');
    Storage::disk('local')->put('territories/stored.png', UploadedFile::fake()->image('stored.png')->getContent());

    foreach (['ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone'] as $permission) {
        auth()->user()->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'name' => 'Via Roma',
        'image_path' => 'territories/stored.png',
    ]);

    // The upload field takes a single file: the stored one is removed before uploading another
    $changes = match ($imageChange) {
        'keep' => [['notes' => 'Only the notes changed']],
        'replace' => [['image_file' => null], ['image_file' => [UploadedFile::fake()->image('new.png')]]],
        'clear' => [['image_file' => null]],
    };

    $page = $isFromZonePage
        ? Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getKey()])
            ->mountAction(TestAction::make('edit')->table($territory))
        : Livewire::test(EditTerritory::class, ['record' => $territory->getKey()]);

    // The stored image is in the upload field, not in the URL field, which only accepts URLs
    $page->assertSchemaStateSet(['image_path' => null]);

    foreach ($changes as $change) {
        $page->fillForm($change);
    }

    ($isFromZonePage ? $page->callMountedAction() : $page->call('save'))
        ->assertHasNoFormErrors();

    $territory->refresh();

    match ($imageChange) {
        'keep' => expect($territory->image_path)->toBe('territories/stored.png')
            ->and($territory->notes)->toBe('Only the notes changed'),
        'replace' => expect($territory->image_path)->toStartWith('territories/')->not->toBe('territories/stored.png'),
        'clear' => expect($territory->image_path)->toBeNull(),
    };

    if ($imageChange === 'replace') {
        Storage::disk('local')->assertExists($territory->image_path);
    }
})->with([
    'edit page' => false,
    'zone page' => true,
])->with(['keep', 'replace', 'clear']);

test('a territory with an image URL keeps it in the URL field when edited', function (string $imageUrl) {
    Storage::fake('local');

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'name' => 'Via Roma',
        'image_path' => $imageUrl,
    ]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->assertSchemaStateSet(['image_path' => $imageUrl])
        ->fillForm(['notes' => 'Only the notes changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($territory->refresh()->image_path)->toBe($imageUrl);
})->with([
    'https' => 'https://example.com/territory.png',
    'uppercase scheme' => 'HTTPS://example.com/territory.png',
]);

test('an image path that is not a stored file stays in the URL field of the edit form', function (string $imagePath) {
    Storage::fake('local');

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'name' => 'Via Roma',
        'image_path' => $imagePath,
    ]);

    // Not moved to the upload field, which would drop it and clear the image on save
    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->assertSchemaStateSet(['image_path' => $imagePath, 'image_file' => null]);
})->with([
    'protocol-relative URL' => '//example.com/territory.png',
    'other scheme' => 'ftp://example.com/territory.png',
    'missing stored file' => 'territories/deleted.png',
    'path leaving the disk' => '../maps/territory.png',
]);

test('the image preview of the edit form shows the stored image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('territories/stored.png', UploadedFile::fake()->image('stored.png')->getContent());

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'name' => 'Via Roma',
        'image_path' => 'territories/stored.png',
    ]);

    $preview = (string) Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->instance()
        ->form
        ->getComponent('image_preview')
        ->getState();

    expect($preview)->toContain('<img src="')
        ->toContain('/territories/stored.png?expiration=');
});

test('the image preview of the form shows a newly uploaded image only when it can be previewed', function (bool $isPreviewable) {
    Storage::fake('local');

    $upload = $isPreviewable
        ? UploadedFile::fake()->image('new.png')
        // Content Livewire cannot preview, under an image name
        : UploadedFile::fake()->create('new.jpg', 10, 'image/heic');

    $page = Livewire::test(CreateTerritory::class)
        ->fillForm(['name' => 'Via Roma', 'image_file' => [$upload]]);

    $preview = $page->instance()->form->getComponent('image_preview')->getState();

    if ($isPreviewable) {
        expect((string) $preview)->toContain('<img src="');
    } else {
        expect((string) $preview)->not->toContain('<img')
            ->toContain(__('filament-resources.territory.missing_image'));
    }
})->with([
    'previewable' => true,
    'not previewable' => false,
]);

test('the map offers the tools that draw areas and lines, but not text labels', function () {
    // Text labels are not shown where the territory is used (assignment, shared page, dashboard)
    Livewire::test(CreateTerritory::class)
        ->assertSchemaComponentExists('location', 'form', checkComponentUsing: function (Map $map): bool {
            $tools = json_decode($map->getMapConfig(), true)['geoMan'];

            return $tools['drawPolygon'] && $tools['drawRectangle'] && $tools['cutPolygon'] && $tools['drawPolyline']
                && ! $tools['drawText'];
        });
});

test('the map of a new territory opens on the configured default centre', function () {
    config(['filament-leaflet.default_map_center' => [39.8017, -89.6436]]);

    Livewire::test(CreateTerritory::class)
        ->assertSchemaComponentExists('location', 'form', checkComponentUsing: fn (Map $map): bool => json_decode($map->getMapConfig(), true)['default'] === ['lat' => 39.8017, 'lng' => -89.6436]);
});

/**
 * Get the centre the map of a form opens on, as [latitude, longitude].
 *
 * @return array{float, float}
 */
function mapDefaultLocation(Map $map): array
{
    $default = json_decode($map->getMapConfig(), true)['default'];

    return [$default['lat'], $default['lng']];
}

test('the map of a new territory opens on the territories that have a map', function () {
    Territory::factory()->for($this->zone, 'territoryZone')->create([
        'geojson' => territoryPolygon([[12.39, 41.99], [12.37, 41.87], [12.45, 41.90], [12.39, 41.99]]),
    ]);

    Livewire::test(CreateTerritory::class)
        ->assertSchemaComponentExists('location', 'form', checkComponentUsing: fn (Map $map): bool => abs(mapDefaultLocation($map)[0] - 41.93) < 0.000001
            && abs(mapDefaultLocation($map)[1] - 12.41) < 0.000001);
});

test('the map of a new territory of a zone opens on the territories of that zone', function () {
    foreach (['ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone'] as $permission) {
        auth()->user()->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $far = TerritoryZone::factory()->create(['name' => 'Far']);
    Territory::factory()->for($far, 'territoryZone')->create([
        'geojson' => territoryPolygon([[-74.30, 41.10], [-74.20, 41.10], [-74.20, 41.20], [-74.30, 41.10]]),
    ]);
    Territory::factory()->for($this->zone, 'territoryZone')->create([
        'geojson' => territoryPolygon([[12.39, 41.99], [12.37, 41.87], [12.45, 41.90], [12.39, 41.99]]),
    ]);

    Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getKey()])
        ->mountAction(TestAction::make('create')->table())
        ->assertSchemaComponentExists('location', 'mountedActionSchema0', checkComponentUsing: fn (Map $map): bool => abs(mapDefaultLocation($map)[0] - 41.93) < 0.000001
            && abs(mapDefaultLocation($map)[1] - 12.41) < 0.000001);
});

/**
 * Get the label of the Map/Image tab a form opens on.
 */
function openMapImageTabLabel(Tabs $tabs): string
{
    $visibleTabs = array_values($tabs->getChildSchema()->getComponents());

    return (string) $visibleTabs[$tabs->getActiveTab() - 1]->getLabel();
}

dataset('territory map and image', [
    'a map' => [['withMap' => true, 'withImage' => false], 'tab_map'],
    'only an image' => [['withMap' => false, 'withImage' => true], 'tab_image'],
    'a map and an image' => [['withMap' => true, 'withImage' => true], 'tab_map'],
    'neither' => [['withMap' => false, 'withImage' => false], 'tab_map'],
]);

/**
 * Create a territory of the zone with a map and/or an image.
 *
 * @param  array{withMap: bool, withImage: bool}  $content
 */
function territoryWithContent(TerritoryZone $zone, array $content): Territory
{
    $factory = Territory::factory()->for($zone, 'territoryZone');

    if ($content['withMap']) {
        $factory = $factory->withMap();
    }

    if ($content['withImage']) {
        $factory = $factory->withImage();
    }

    return $factory->create();
}

test('the edit page of a territory opens on the tab of its map or image', function (array $content, string $expectedTab) {
    $territory = territoryWithContent($this->zone, $content);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->assertSchemaComponentExists('mapImageTabs', 'form', checkComponentUsing: fn (Tabs $tabs): bool => openMapImageTabLabel($tabs) === __("filament-resources.territory.{$expectedTab}"));
})->with('territory map and image');

test('the view page of a territory opens on the tab of its map or image', function (array $content, string $expectedTab) {
    $territory = territoryWithContent($this->zone, $content);

    $page = Livewire::test(ViewTerritory::class, ['record' => $territory->getKey()]);

    if (! $content['withMap'] && ! $content['withImage']) {
        // The view page shows no tabs for a territory with neither a map nor an image
        $page->assertSchemaComponentHidden('mapImageTabs', 'form');

        return;
    }

    $page->assertSchemaComponentExists('mapImageTabs', 'form', checkComponentUsing: fn (Tabs $tabs): bool => openMapImageTabLabel($tabs) === __("filament-resources.territory.{$expectedTab}"));
})->with('territory map and image');

test('the territories page of a zone opens a territory on the tab of its map or image', function (array $content, string $expectedTab) {
    foreach (['ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone'] as $permission) {
        auth()->user()->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $territory = territoryWithContent($this->zone, $content);

    Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getKey()])
        ->mountAction(TestAction::make('edit')->table($territory))
        ->assertSchemaComponentExists('mapImageTabs', 'mountedActionSchema0', checkComponentUsing: fn (Tabs $tabs): bool => openMapImageTabLabel($tabs) === __("filament-resources.territory.{$expectedTab}"));
})->with('territory map and image');

test('the form of a new territory opens on the map tab', function () {
    Livewire::test(CreateTerritory::class)
        ->assertSchemaComponentExists('mapImageTabs', 'form', checkComponentUsing: fn (Tabs $tabs): bool => openMapImageTabLabel($tabs) === __('filament-resources.territory.tab_map'));
});
