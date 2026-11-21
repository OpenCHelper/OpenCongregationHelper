<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\TerritoryCoverage;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Settings\TerritorySettings;
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

test('the coverage counts the territories with an assignment returned in the period or in progress', function () {
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
        ->worked->toBe(3)
        ->total->toBe(5)
        ->percentage->toBe(60.0)
        ->label->toBe(__('filament-widgets.territory_coverage.value', ['percentage' => '60%', 'worked' => 3, 'total' => 5]));
});

test('an assignment in progress counts however long ago it started, but not before it starts', function () {
    ($this->assign)(($this->territory)('Out for long'), '2023-01-01', null);
    ($this->assign)(($this->territory)('Not started yet'), '2026-07-01', null);

    expect(Livewire::test(TerritoryCoverage::class)->set('months', 6)->instance()->getCoverage())
        ->worked->toBe(1)
        ->total->toBe(2);
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
    expect(Livewire::test(TerritoryCoverage::class)->instance()->getPeriodOptions())->toHaveKeys([6, 12, 24]);

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
    $inProgressForCampaign = ($this->territory)('Campaign in progress');
    $worked = ($this->territory)('Regular');
    $inProgress = ($this->territory)('Regular in progress');
    ($this->assign)($workedForCampaign, '2026-03-01', '2026-03-20', $campaign);
    ($this->assign)($inProgressForCampaign, '2026-05-01', null, $campaign);
    ($this->assign)($worked, '2026-03-01', '2026-03-20');
    ($this->assign)($inProgress, '2026-05-01', null);

    $widget = Livewire::test(TerritoryCoverage::class)->instance();

    expect($widget->getCoverage())->worked->toBe(4)->total->toBe(4)
        ->and($widget->getCoverageExcludingCampaigns())->worked->toBe(2)->total->toBe(4)->percentage->toBe(50.0);
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

test('the territories never worked come first, then those not worked for the longest time, and those in progress last', function () {
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

    expect($names)->toBe(['Never A', 'Never B', 'Older', 'Old', 'Recent']);
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

test('the periods and the default one are the ones of the settings', function () {
    $settings = app(TerritorySettings::class);
    $settings->coverage_periods_months = [3, 36];
    $settings->coverage_default_period_months = 36;
    $settings->save();

    $territory = ($this->territory)('Two years ago');
    ($this->assign)($territory, '2024-05-01', '2024-06-01');

    Livewire::test(TerritoryCoverage::class)
        ->assertSet('months', 36)
        ->assertSee(trans_choice('filament-widgets.territory_coverage.period', 3, ['count' => 3]))
        ->assertDontSee(trans_choice('filament-widgets.territory_coverage.period', 12, ['count' => 12]))
        ->assertSee(__('filament-widgets.territory_coverage.value', ['percentage' => '100%', 'worked' => 1, 'total' => 1]))
        ->set('months', 12)
        ->assertSet('months', 36)
        ->set('months', 3)
        ->assertSee(__('filament-widgets.territory_coverage.value', ['percentage' => '0%', 'worked' => 0, 'total' => 1]));
});

test('a period no longer offered falls back on the default one', function () {
    $settings = app(TerritorySettings::class);
    $settings->coverage_periods_months = [6, 18];
    $settings->coverage_default_period_months = 18;
    $settings->save();

    Livewire::test(TerritoryCoverage::class, ['months' => 12])->assertSet('months', 18);
});

test('the territories in progress are marked as such in the list', function () {
    ($this->assign)(($this->territory)('Out now'), '2026-05-01', null);

    Livewire::test(TerritoryCoverage::class)
        ->assertSeeInOrder(['Out now', __('filament-widgets.territory_coverage.in_progress')]);
});

test('the period counts whole calendar months, also at the end of a month', function (string $today, int $months, string $returnedAt) {
    $this->travelTo(Carbon::parse($today.' 12:00:00'));
    ($this->assign)(($this->territory)('Returned'), Carbon::parse($returnedAt)->subDays(10)->toDateString(), $returnedAt);

    $settings = app(TerritorySettings::class);
    $settings->coverage_periods_months = [1, 6, 12];
    $settings->save();

    expect(Livewire::test(TerritoryCoverage::class)->set('months', $months)->instance()->getCoverage()['worked'])->toBe(1);
})->with([
    'last month on 31 March' => ['2026-03-31', 1, '2026-03-01'],
    'last 6 months on 31 August' => ['2026-08-31', 6, '2026-03-01'],
]);

test('the percentage is formatted in the language of the user', function (string $locale, string $expected) {
    app()->setLocale($locale);
    foreach (range(1, 9) as $number) {
        $territory = ($this->territory)("Territory {$number}");

        if ($number === 1) {
            ($this->assign)($territory, '2026-03-01', '2026-03-20');
        }
    }

    expect(Livewire::test(TerritoryCoverage::class)->instance()->getCoverage()['label'])->toStartWith($expected);
})->with([
    'English' => ['en', '11.1%'],
    'Italian' => ['it', '11,1%'],
    'Spanish' => ['es', '11,1'],
]);

test('long territory names are truncated with their full name as tooltip', function () {
    ($this->territory)('Country Club Road (east)');

    Livewire::test(TerritoryCoverage::class)
        ->assertSeeHtml('title="Country Club Road (east)"');
});

test('the columns of the widget never grow wider than the screen', function () {
    ($this->territory)('Country Club Road (east) and Old Mill Road');

    // Without an explicit single column, the implicit one is as wide as the longest (non-wrapping) territory name
    Livewire::test(TerritoryCoverage::class)
        ->assertSeeHtml('grid grid-cols-1 gap-6 md:grid-cols-2')
        ->assertSeeHtml('class="flex min-w-0 flex-col gap-4"')
        ->assertSeeHtml('class="min-w-0" data-least-recently-worked');
});
