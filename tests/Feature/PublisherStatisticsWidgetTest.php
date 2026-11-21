<?php

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Widgets\PublisherStatistics;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->viewer = User::factory()->create();
    foreach (['ViewAny:User', 'View:User', 'ViewAny:TerritoryAssignment', 'ViewAny:Territory', 'View:Territory'] as $permission) {
        $this->viewer->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->viewer);

    $this->publisher = User::factory()->create();
});

/**
 * Create the given number of returned assignments of a territory to a publisher.
 */
function assignTerritoryTimes(Territory $territory, User $publisher, int $times): void
{
    TerritoryAssignment::factory()->count($times)->create([
        'territory_id' => $territory->id,
        'assignee_id' => $publisher->id,
        'started_at' => now()->subMonth(),
        'ended_at' => now()->subWeek(),
    ]);
}

test('the publisher page shows the statistics widget', function () {
    Livewire::test(ViewUser::class, ['record' => $this->publisher->getRouteKey()])
        ->assertOk()
        ->assertSeeLivewire(PublisherStatistics::class);
});

test('a message is shown when the publisher has no assignments', function () {
    Livewire::test(PublisherStatistics::class, ['record' => $this->publisher])
        ->assertOk()
        ->assertSee(__('filament-widgets.publisher_statistics.no_assignments'))
        ->assertDontSee(__('filament-widgets.publisher_statistics.preferred_zones'));
});

test('the zones are ranked by how often the publisher takes their territories', function () {
    $northZone = TerritoryZone::factory()->create(['name' => 'North zone']);
    $southZone = TerritoryZone::factory()->create(['name' => 'South zone']);
    $unusedZone = TerritoryZone::factory()->create(['name' => 'Unused zone']);

    assignTerritoryTimes(Territory::factory()->for($northZone, 'territoryZone')->create(), $this->publisher, 2);
    assignTerritoryTimes(Territory::factory()->for($northZone, 'territoryZone')->create(), $this->publisher, 1);
    assignTerritoryTimes(Territory::factory()->for($southZone, 'territoryZone')->create(), $this->publisher, 1);
    Territory::factory()->for($unusedZone, 'territoryZone')->create();

    $widget = Livewire::test(PublisherStatistics::class, ['record' => $this->publisher])->instance();

    expect($widget->getAssignmentsCount())->toBe(4)
        ->and($widget->getPreferredZones()->map(fn (array $zone): array => [$zone['name'], $zone['count'], $zone['percentage']])->all())
        ->toBe([
            ['North zone', 3, 75.0],
            ['South zone', 1, 25.0],
        ]);
});

test('the territories are ranked by how often the publisher takes them', function () {
    $zone = TerritoryZone::factory()->create();
    $favourite = Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'Favourite']);
    $occasional = Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'Occasional']);

    assignTerritoryTimes($occasional, $this->publisher, 1);
    assignTerritoryTimes($favourite, $this->publisher, 3);

    // Assignments of other publishers are not counted
    assignTerritoryTimes($occasional, User::factory()->create(), 5);

    Livewire::test(PublisherStatistics::class, ['record' => $this->publisher])
        ->assertSeeInOrder([
            'Favourite',
            trans_choice('filament-widgets.publisher_statistics.assignments_count', 3),
            '75%',
            'Occasional',
            trans_choice('filament-widgets.publisher_statistics.assignments_count', 1),
            '25%',
        ]);
});

test('only the most assigned territories are listed', function () {
    $zone = TerritoryZone::factory()->create();

    foreach (range(1, PublisherStatistics::PREFERRED_TERRITORIES_LIMIT + 2) as $index) {
        assignTerritoryTimes(Territory::factory()->for($zone, 'territoryZone')->create(), $this->publisher, $index);
    }

    $territories = Livewire::test(PublisherStatistics::class, ['record' => $this->publisher])
        ->instance()
        ->getPreferredTerritories();

    expect($territories)->toHaveCount(PublisherStatistics::PREFERRED_TERRITORIES_LIMIT)
        ->and($territories->first()['count'])->toBe(PublisherStatistics::PREFERRED_TERRITORIES_LIMIT + 2);
});

test('deleted assignments are ignored and deleted territories are not linked', function () {
    $zone = TerritoryZone::factory()->create();
    $territory = Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'Removed territory']);
    assignTerritoryTimes($territory, $this->publisher, 2);
    TerritoryAssignment::query()->where('assignee_id', $this->publisher->id)->first()->delete();
    $territory->delete();

    $territories = Livewire::test(PublisherStatistics::class, ['record' => $this->publisher])
        ->instance()
        ->getPreferredTerritories();

    expect($territories->sole())
        ->name->toBe('Removed territory')
        ->count->toBe(1)
        ->url->toBeNull();
});

test('users who cannot see all the assignments only get statistics of their own', function () {
    $zone = TerritoryZone::factory()->create();
    assignTerritoryTimes(Territory::factory()->for($zone, 'territoryZone')->create(), $this->publisher, 2);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('View:User', 'web'));
    $this->actingAs($viewer);

    Livewire::test(PublisherStatistics::class, ['record' => $this->publisher])
        ->assertSee(__('filament-widgets.publisher_statistics.no_assignments'));
});
