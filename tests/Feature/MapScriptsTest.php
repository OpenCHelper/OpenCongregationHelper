<?php

use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Filament\Widgets\TerritoryGlobalMap;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * The map picker of the territory form and the maps of `eduardoribeirodev/filament-leaflet` each bundle their own
 * Leaflet, which their plugins use as `window.L`: loaded on the same page, drawing on a map fails. Each script is
 * loaded only with one of its maps.
 */

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    $this->withoutVite();

    // The assets already added by the requests of the other tests would be skipped
    Livewire::flushState();

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach (['ViewAny:Territory', 'View:Territory', 'Create:Territory', 'Update:Territory', 'ViewAny:TerritoryZone', 'View:TerritoryZone', 'ViewAny:TerritoryAssignment', 'View:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->leafletMapScript = FilamentAsset::getScriptSrc('leaflet-map', 'eduardoribeirodev/filament-leaflet');
    $this->mapPickerScript = FilamentAsset::getScriptSrc('filament-map-picker-scripts', 'dotswan/filament-map-picker');
});

/**
 * The key by which Livewire remembers the `@assets` block of a view sent to the page.
 */
function mapAssetsKey(string $view): string
{
    return crc32(resource_path("views/filament/{$view}.blade.php")).'-0';
}

test('the territory form loads only the script of the map picker', function (string $page) {
    $territory = Territory::factory()->withMap()->for(TerritoryZone::factory(), 'territoryZone')->create();

    // Added once, before Livewire starts and initialises the map
    $response = $this->get(TerritoryResource::getUrl($page, $page === 'edit' ? ['record' => $territory] : []))
        ->assertOk()
        ->assertSeeInOrder([$this->mapPickerScript, '/livewire.js'], escape: false)
        ->assertDontSee($this->leafletMapScript, escape: false);

    expect(substr_count($response->getContent(), $this->mapPickerScript))->toBe(1);
})->with(['create', 'edit']);

test('the territory form in a modal loads the script of the map picker', function (string $action) {
    $zone = TerritoryZone::factory()->create();
    $territory = Territory::factory()->withMap()->for($zone, 'territoryZone')->create();

    $component = Livewire::test(ManageZoneTerritories::class, ['record' => $zone->getKey()]);

    expect($component->snapshot['memo']['assets'] ?? [])->toBe([]);

    // The create action is in the header of the table, the others on its records
    $component->mountAction(TestAction::make($action)->table($action === 'create' ? true : $territory));

    expect($component->snapshot['memo']['assets'] ?? [])->toBe([mapAssetsKey('map-picker-assets')]);
})->with(['create', 'edit', 'view']);

test('the page of an assignment loads only the script of its map', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->withMap()->for(TerritoryZone::factory(), 'territoryZone')->create()->getKey(),
        'assignee_id' => User::factory()->create()->getKey(),
    ]);

    $response = $this->get(TerritoryAssignmentResource::getUrl('view', ['record' => $assignment]))
        ->assertOk()
        ->assertSeeInOrder([$this->leafletMapScript, '/livewire.js'], escape: false)
        ->assertDontSee($this->mapPickerScript, escape: false);

    expect(substr_count($response->getContent(), $this->leafletMapScript))->toBe(1);
});

test('the shared page of an assignment loads only the script of its map', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->withMap()->for(TerritoryZone::factory(), 'territoryZone')->create()->getKey(),
        'assignee_id' => User::factory()->create()->getKey(),
        'ended_at' => null,
    ]);
    $sharedUrl = $assignment->share();

    auth()->logout();

    $response = $this->get('/guest/shared-assignment/'.$sharedUrl->uid)
        ->assertOk()
        ->assertSeeInOrder([$this->leafletMapScript, '/livewire.js'], escape: false)
        ->assertDontSee($this->mapPickerScript, escape: false);

    expect(substr_count($response->getContent(), $this->leafletMapScript))->toBe(1);
});

test('the dashboard map loads only the script of its map', function () {
    auth()->user()->givePermissionTo(Permission::findOrCreate('View:TerritoryGlobalMap', 'web'));

    $component = Livewire::withoutLazyLoading()->test(TerritoryGlobalMap::class)->assertOk();

    expect($component->snapshot['memo']['assets'] ?? [])->toBe([mapAssetsKey('leaflet-map-assets')]);
});

test('the assets of the maps add their script', function (string $view, string $scriptId, string $package) {
    view("filament.{$view}")->render();

    expect(SupportScriptsAndAssets::$nonLivewireAssets)->toHaveCount(1)
        ->and(implode(SupportScriptsAndAssets::$nonLivewireAssets))->toContain(FilamentAsset::getScriptSrc($scriptId, $package));
})->with([
    'filament-leaflet' => ['leaflet-map-assets', 'leaflet-map', 'eduardoribeirodev/filament-leaflet'],
    'map picker' => ['map-picker-assets', 'filament-map-picker-scripts', 'dotswan/filament-map-picker'],
]);
