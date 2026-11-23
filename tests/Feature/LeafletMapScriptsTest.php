<?php

use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Widgets\TerritoryGlobalMap;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * The map picker of the territory form and the maps of `eduardoribeirodev/filament-leaflet` each bundle their own
 * Leaflet: loaded on the same page, drawing on the map picker fails. The maps' script is loaded only with a map.
 */

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    $this->withoutVite();

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach (['ViewAny:Territory', 'View:Territory', 'Create:Territory', 'Update:Territory', 'ViewAny:TerritoryAssignment', 'View:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->leafletMapScript = FilamentAsset::getScriptSrc('leaflet-map', 'eduardoribeirodev/filament-leaflet');
    $this->mapPickerScript = FilamentAsset::getScriptSrc('filament-map-picker-scripts', 'dotswan/filament-map-picker');
});

test('the territory form loads the map picker without the script of the other maps', function (string $page) {
    $territory = Territory::factory()->withMap()->for(TerritoryZone::factory(), 'territoryZone')->create();

    $this->get(TerritoryResource::getUrl($page, $page === 'edit' ? ['record' => $territory] : []))
        ->assertOk()
        ->assertSee($this->mapPickerScript, escape: false)
        ->assertDontSee($this->leafletMapScript, escape: false);
})->with(['create', 'edit']);

test('the page of an assignment loads the script of its map', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->withMap()->for(TerritoryZone::factory(), 'territoryZone')->create()->getKey(),
        'assignee_id' => User::factory()->create()->getKey(),
    ]);

    // Added once, before Livewire starts and initialises the map
    $response = $this->get(TerritoryAssignmentResource::getUrl('view', ['record' => $assignment]))
        ->assertOk()
        ->assertSeeInOrder([$this->leafletMapScript, '/livewire.js'], escape: false);

    expect(substr_count($response->getContent(), $this->leafletMapScript))->toBe(1);
});

test('the dashboard map loads the script of its map', function () {
    // The assets already added by the requests of the other tests would be skipped
    Livewire::flushState();

    $component = Livewire::withoutLazyLoading()->test(TerritoryGlobalMap::class)->assertOk();

    // Livewire remembers the `@assets` blocks sent to the page by the key of their view
    $assetsKey = crc32(resource_path('views/filament/leaflet-map-assets.blade.php')).'-0';

    expect($component->snapshot['memo']['assets'] ?? [])->toBe([$assetsKey]);
});

test('the assets of the maps add their script', function () {
    Livewire::flushState();

    view('filament.leaflet-map-assets')->render();

    expect(SupportScriptsAndAssets::$nonLivewireAssets)->toHaveCount(1)
        ->and(implode(SupportScriptsAndAssets::$nonLivewireAssets))->toContain($this->leafletMapScript);
});
