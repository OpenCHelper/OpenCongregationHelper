<?php

use App\Filament\Widgets\StatsOverview;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

    $this->actingAs(User::factory()->create(['id' => 1])->givePermissionTo(Permission::findOrCreate('ViewAny:Territory', 'web')));

    $this->assignee = User::factory()->create();
    $this->territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    $this->assign = fn (string $startedAt, ?string $endedAt): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => Carbon::parse($startedAt),
        'ended_at' => $endedAt !== null ? Carbon::parse($endedAt) : null,
    ]);
});

/**
 * Get the "average assignment duration" stat shown by the dashboard widget.
 */
function averageAssignmentDurationStat(): Stat
{
    $widget = Livewire::test(StatsOverview::class)->assertOk()->instance();

    return (fn (): array => $this->getStats())->call($widget)[1];
}

test('the average assignment duration is the mean duration of the returned assignments', function (array $assignments) {
    foreach ($assignments as [$startedAt, $endedAt]) {
        ($this->assign)($startedAt, $endedAt);
    }

    expect(averageAssignmentDurationStat()->getValue())->toBe('30 '.__('filament-resources.stats_widget.days'));
})->with([
    'a single assignment' => [[
        ['2026-03-01 09:00:00', '2026-03-31 18:00:00'],
    ]],
    // A mean of the monthly means would ignore how many assignments were returned each month
    'assignments returned in different months' => [[
        ['2026-02-01 10:00:00', '2026-02-11 10:00:00'],
        ['2026-02-05 09:00:00', '2026-02-25 09:00:00'],
        ['2026-03-01 08:00:00', '2026-04-30 08:00:00'],
    ]],
]);

test('the average assignment duration ignores the assignments that are not returned, old or inconsistent', function () {
    ($this->assign)('2026-03-01 09:00:00', '2026-03-31 18:00:00');

    // Returned more than 365 days ago
    ($this->assign)('2025-01-01 09:00:00', '2025-05-01 09:00:00');
    // Deleted
    ($this->assign)('2026-01-01 09:00:00', '2026-05-01 09:00:00')->delete();
    // Ended before it started (saved before the dates were validated)
    TerritoryAssignment::withoutEvents(fn (): TerritoryAssignment => ($this->assign)('2026-05-01 09:00:00', '2026-01-01 09:00:00'));
    // In progress
    ($this->assign)('2026-06-01 09:00:00', null);

    $stat = averageAssignmentDurationStat();

    expect($stat->getValue())->toBe('30 '.__('filament-resources.stats_widget.days'))
        ->and($stat->getChart())->each->toBeGreaterThanOrEqual(0);
});

test('no average assignment duration is shown when no assignment has been returned', function () {
    ($this->assign)('2026-06-01 09:00:00', null);

    expect(averageAssignmentDurationStat()->getValue())->toBe('—');
});
