<?php

use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ViewTerritory;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->freezeTime();
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach (['ViewAny:Territory', 'View:Territory', 'Update:Territory'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();
});

/**
 * Create an assignment of the test territory between the given number of days ago.
 */
function coverageOfTerritory(Territory $territory, int $startedDaysAgo, ?int $endedDaysAgo): TerritoryAssignment
{
    return TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => User::factory()->create()->id,
        'started_at' => now()->subDays($startedDaysAgo),
        'ended_at' => $endedDaysAgo === null ? null : now()->subDays($endedDaysAgo),
    ]);
}

test('the average coverage time only counts the returned assignments', function () {
    coverageOfTerritory($this->territory, 100, 90);
    coverageOfTerritory($this->territory, 80, 50);
    coverageOfTerritory($this->territory, 20, null);

    expect($this->territory->averageCoverageDays())->toEqual(20.0);
});

test('a territory never returned has no average coverage time', function () {
    coverageOfTerritory($this->territory, 20, null);

    expect($this->territory->averageCoverageDays())->toBeNull();
});

test('the average coverage time ignores deleted assignments', function () {
    coverageOfTerritory($this->territory, 100, 90);
    coverageOfTerritory($this->territory, 80, 50)->delete();

    expect($this->territory->averageCoverageDays())->toEqual(10.0);
});

test('the average coverage time is formatted in days', function (?float $days, ?string $expected) {
    app()->setLocale('en');

    expect(TerritoryResource::formatAverageCoverageTime($days))->toBe($expected);
})->with([
    'never covered' => [null, null],
    'less than a day' => [0.3, 'Less than a day'],
    'one day' => [1.2, '1 day'],
    'rounded days' => [14.6, '15 days'],
]);

test('the territory page shows the average coverage time', function () {
    coverageOfTerritory($this->territory, 100, 90);
    coverageOfTerritory($this->territory, 80, 50);

    Livewire::test(ViewTerritory::class, ['record' => $this->territory->getKey()])
        ->assertSee(__('filament-resources.territory.field_average_coverage_time'))
        ->assertSee(trans_choice('filament-resources.territory.assignment_duration_day', 20, ['count' => 20]));
});

test('the territory page tells when the territory was never covered', function () {
    Livewire::test(ViewTerritory::class, ['record' => $this->territory->getKey()])
        ->assertSee(__('filament-resources.territory.average_coverage_time_never_covered'));
});

test('the edit page does not show the average coverage time', function () {
    Livewire::test(EditTerritory::class, ['record' => $this->territory->getKey()])
        ->assertDontSee(__('filament-resources.territory.field_average_coverage_time'));
});
