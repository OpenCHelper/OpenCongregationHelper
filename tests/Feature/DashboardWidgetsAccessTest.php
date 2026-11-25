<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\NoDashboardAccess;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TerritoryCoverage;
use App\Filament\Widgets\TerritoryGlobalMap;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Database\Seeders\FakeRoleSeeder;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    $this->seed([ShieldSeeder::class, FakeRoleSeeder::class]);

    User::factory()->create(['id' => 1]);

    Territory::factory()->withMap()->for(TerritoryZone::factory()->create(['name' => 'Secret zone']), 'territoryZone')
        ->create(['name' => 'Secret territory']);
});

/**
 * Get the widgets of the dashboard that the given user can see.
 *
 * @return list<class-string>
 */
function visibleDashboardWidgets(User $user): array
{
    test()->actingAs($user);

    return array_values(array_filter((new Dashboard)->getWidgets(), fn (string $widget): bool => $widget::canView()));
}

test('a user with no role sees neither the territories nor their statistics on the dashboard', function () {
    $user = User::factory()->create();

    expect(visibleDashboardWidgets($user))->toBe([NoDashboardAccess::class]);

    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee(__('filament-widgets.no_dashboard_access.description'))
        ->assertDontSeeLivewire(StatsOverview::class)
        ->assertDontSeeLivewire(TerritoryCoverage::class)
        ->assertDontSeeLivewire(TerritoryGlobalMap::class)
        ->assertDontSee('Secret territory')
        ->assertDontSee('Secret zone');
});

test('a user with no role cannot load the dashboard widgets directly', function (string $widget) {
    $this->actingAs(User::factory()->create());

    Livewire::test($widget)->assertForbidden();
})->with([StatsOverview::class, TerritoryCoverage::class, TerritoryGlobalMap::class]);

test('the seeded roles and the super admins see every dashboard widget', function (string $role) {
    $user = User::factory()->create()->assignRole($role);

    expect(visibleDashboardWidgets($user))->toBe(NoDashboardAccess::DASHBOARD_WIDGETS);

    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSeeLivewire(StatsOverview::class)
        ->assertSeeLivewire(TerritoryCoverage::class)
        ->assertSeeLivewire(TerritoryGlobalMap::class)
        ->assertDontSee(__('filament-widgets.no_dashboard_access.description'));
})->with(['super_admin', 'publisher', 'group_overseer', 'service_overseer', 'territory_servant']);

test('the map also needs its own permission', function () {
    $user = User::factory()->create()->givePermissionTo(Permission::findByName('ViewAny:Territory', 'web'));

    expect(visibleDashboardWidgets($user))->toBe([StatsOverview::class, TerritoryCoverage::class]);

    $user->givePermissionTo(Permission::findByName('View:TerritoryGlobalMap', 'web'));

    expect(visibleDashboardWidgets($user->refresh()))->toBe(NoDashboardAccess::DASHBOARD_WIDGETS);
});

test('the permission of the map alone does not show the territories', function () {
    $user = User::factory()->create()->givePermissionTo(Permission::findByName('View:TerritoryGlobalMap', 'web'));

    expect(visibleDashboardWidgets($user))->toBe([NoDashboardAccess::class]);
});
