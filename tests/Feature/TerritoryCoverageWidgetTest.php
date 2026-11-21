<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\TerritoryCoverage;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

    $this->actingAs(User::factory()->create(['id' => 1]));

    $this->assignee = User::factory()->create();
    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);

    $this->territory = fn (string $name): Territory => Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => $name]);

    $this->assign = fn (Territory $territory, string $startedAt, ?string $endedAt, ?Campaign $campaign = null): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $this->assignee->id,
        'campaign_id' => $campaign?->id,
        'started_at' => Carbon::parse($startedAt),
        'ended_at' => $endedAt !== null ? Carbon::parse($endedAt) : null,
    ]);
});

test('the dashboard shows the territory coverage widget', function () {
    expect((new Dashboard)->getWidgets())->toContain(TerritoryCoverage::class);

    Livewire::test(TerritoryCoverage::class)
        ->assertOk()
        ->assertSee(__('filament-widgets.territory_coverage.heading'));
});

test('the coverage counts the territories with an assignment returned in the period', function () {
    $worked = ($this->territory)('Worked');
    $workedTwice = ($this->territory)('Worked twice');
    $workedLongAgo = ($this->territory)('Worked long ago');
    $inProgress = ($this->territory)('In progress');
    ($this->territory)('Never worked');

    ($this->assign)($worked, '2026-03-01', '2026-03-20');
    ($this->assign)($workedTwice, '2025-08-01', '2025-09-01');
    ($this->assign)($workedTwice, '2026-01-01', '2026-02-01');
    ($this->assign)($workedLongAgo, '2025-01-01', '2025-02-01');
    ($this->assign)($inProgress, '2026-06-01', null);

    $coverage = Livewire::test(TerritoryCoverage::class)->instance()->getCoverage();

    expect($coverage)
        ->worked->toBe(2)
        ->total->toBe(5)
        ->percentage->toBe(40.0)
        ->label->toBe(__('filament-widgets.territory_coverage.value', ['percentage' => '40%', 'worked' => 2, 'total' => 5]));
});

test('the coverage ignores deleted and inconsistent assignments and deleted territories', function () {
    $territory = ($this->territory)('Territory');
    $deletedTerritory = ($this->territory)('Deleted');

    ($this->assign)($territory, '2026-03-01', '2026-03-20')->delete();
    // Ended before it started (saved before the dates were validated)
    TerritoryAssignment::withoutEvents(fn (): TerritoryAssignment => ($this->assign)($territory, '2026-05-01', '2026-01-01'));
    ($this->assign)($deletedTerritory, '2026-03-01', '2026-03-20');
    $deletedTerritory->delete();

    expect(Livewire::test(TerritoryCoverage::class)->instance()->getCoverage())
        ->worked->toBe(0)
        ->total->toBe(1);
});

test('the coverage depends on the chosen period', function (int $months, int $expectedWorked) {
    $territory = ($this->territory)('Eight months ago');
    ($this->assign)($territory, '2025-09-15', '2025-10-15');
    ($this->territory)('Never worked');

    $coverage = Livewire::test(TerritoryCoverage::class)
        ->set('months', $months)
        ->instance()
        ->getCoverage();

    expect($coverage['worked'])->toBe($expectedWorked);
})->with([
    '6 months' => [6, 0],
    '12 months' => [12, 1],
    '24 months' => [24, 1],
]);

test('the period defaults to 12 months and only accepts the offered periods', function () {
    Livewire::test(TerritoryCoverage::class)
        ->assertSet('months', 12)
        ->set('months', 7)
        ->assertSet('months', 12)
        ->set('months', 6)
        ->assertSet('months', 6);
});

test('the coverage excluding campaigns ignores the assignments of campaigns', function () {
    $campaign = Campaign::factory()->create();

    $workedForCampaign = ($this->territory)('Campaign');
    $worked = ($this->territory)('Regular');
    ($this->assign)($workedForCampaign, '2026-03-01', '2026-03-20', $campaign);
    ($this->assign)($worked, '2026-03-01', '2026-03-20');

    $widget = Livewire::test(TerritoryCoverage::class)->instance();

    expect($widget->getCoverage())->worked->toBe(2)->total->toBe(2)
        ->and($widget->getCoverageExcludingCampaigns())->worked->toBe(1)->total->toBe(2)->percentage->toBe(50.0);
});

test('the percentage is rounded down so that 100% means every territory was worked', function () {
    foreach (range(1, 3) as $number) {
        $territory = ($this->territory)("Territory {$number}");

        if ($number < 3) {
            ($this->assign)($territory, '2026-03-01', '2026-03-20');
        }
    }

    expect(Livewire::test(TerritoryCoverage::class)->instance()->getCoverage()['percentage'])->toBe(66.6);
});

test('no territory gives a coverage of zero', function () {
    expect(Livewire::test(TerritoryCoverage::class)->assertOk()->instance()->getCoverage())
        ->worked->toBe(0)
        ->total->toBe(0)
        ->percentage->toBe(0.0);
});

test('the territories never worked come first, then those not worked for the longest time', function () {
    $recent = ($this->territory)('Recent');
    $old = ($this->territory)('Old');
    $older = ($this->territory)('Older');
    $inProgressOnly = ($this->territory)('In progress only');
    ($this->territory)('Never B');
    ($this->territory)('Never A');
    $mostRecent = ($this->territory)('Most recent');

    ($this->assign)($recent, '2026-04-01', '2026-04-20');
    ($this->assign)($old, '2025-01-01', '2025-02-01');
    ($this->assign)($old, '2025-12-01', '2025-12-20');
    ($this->assign)($older, '2024-01-01', '2024-03-01');
    ($this->assign)($inProgressOnly, '2026-06-01', null);
    ($this->assign)($mostRecent, '2026-05-01', '2026-06-01');

    $names = Livewire::test(TerritoryCoverage::class)
        ->instance()
        ->getLeastRecentlyWorkedTerritories()
        ->pluck('name')
        ->all();

    expect($names)->toBe(['In progress only', 'Never A', 'Never B', 'Older', 'Old']);
});

test('the widget lists the territories never worked or not worked for the longest time', function () {
    $old = ($this->territory)('Old territory');
    ($this->assign)($old, '2025-01-01', '2025-02-01');
    ($this->territory)('Unworked territory');

    Livewire::test(TerritoryCoverage::class)
        ->assertSeeInOrder([
            'Unworked territory',
            __('filament-widgets.territory_coverage.never_worked'),
            'Old territory',
            Carbon::parse('2025-02-01')->isoFormat('LL'),
        ])
        ->assertSee(__('filament-widgets.territory_coverage.value', ['percentage' => '0%', 'worked' => 0, 'total' => 2]));
});
