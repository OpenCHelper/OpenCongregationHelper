<?php

use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:Territory', 'web'));
    $this->actingAs($user);

    $this->assignee = User::factory()->create();
    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);
});

/**
 * Create a territory, optionally assigned since the given number of days.
 */
function territoryAssignedSince(TerritoryZone $zone, User $assignee, ?int $days = null, ?int $returnedDaysAgo = null): Territory
{
    $territory = Territory::factory()->for($zone, 'territoryZone')->create();

    if ($days !== null) {
        TerritoryAssignment::factory()->create([
            'territory_id' => $territory->id,
            'assignee_id' => $assignee->id,
            'started_at' => now()->subDays($days),
            'ended_at' => $returnedDaysAgo !== null ? now()->subDays($returnedDaysAgo) : null,
        ]);
    }

    return $territory;
}

test('territories can be sorted by assignment duration on any database', function () {
    $unassigned = territoryAssignedSince($this->zone, $this->assignee);
    $assignedRecently = territoryAssignedSince($this->zone, $this->assignee, days: 5);
    $assignedLongAgo = territoryAssignedSince($this->zone, $this->assignee, days: 30);

    Livewire::test(ListTerritories::class)
        ->sortTable('assignmentDuration')
        ->assertCanSeeTableRecords([$unassigned, $assignedRecently, $assignedLongAgo], inOrder: true)
        ->sortTable('assignmentDuration', 'desc')
        ->assertCanSeeTableRecords([$assignedLongAgo, $assignedRecently, $unassigned], inOrder: true);
});

test('the duration column shows when unassigned territories were last returned', function () {
    $territory = territoryAssignedSince($this->zone, $this->assignee, days: 40, returnedDaysAgo: 3);

    Livewire::test(ListTerritories::class)
        ->assertTableColumnStateSet(
            'assignmentDuration',
            trans_choice('filament-resources.territory.assignment_ended_day_ago', 3, ['count' => 3]),
            $territory,
        );
});

test('zone groups show the number of territories of each zone', function () {
    $south = TerritoryZone::factory()->create(['name' => 'South']);

    territoryAssignedSince($this->zone, $this->assignee);
    territoryAssignedSince($this->zone, $this->assignee);
    territoryAssignedSince($south, $this->assignee);

    Livewire::test(ListTerritories::class)
        ->set('tableGrouping', 'territoryZone.name')
        ->assertSee('North (2)')
        ->assertSee('South (1)');
});

test('zone group counts follow the table search and filters', function () {
    $south = TerritoryZone::factory()->create(['name' => 'South']);

    territoryAssignedSince($this->zone, $this->assignee)->update(['name' => 'Via Roma 1']);
    territoryAssignedSince($this->zone, $this->assignee, days: 5)->update(['name' => 'Via Roma 2']);
    territoryAssignedSince($this->zone, $this->assignee)->update(['name' => 'Via Milano']);
    territoryAssignedSince($south, $this->assignee, days: 5)->update(['name' => 'Via Roma 3']);

    Livewire::test(ListTerritories::class)
        ->set('tableGrouping', 'territoryZone.name')
        ->assertSee('North (3)')
        ->assertSee('South (1)')
        ->searchTable('Roma')
        ->assertSee('North (2)')
        ->assertSee('South (1)')
        ->filterTable('assignment_status', true)
        ->assertSee('North (1)')
        ->assertSee('South (1)');
});

test('campaign groups count the territories shown below them', function () {
    $alpha = Campaign::factory()->create(['name' => 'Alpha']);

    // Returned assignments of the campaign must not be counted
    $returned = territoryAssignedSince($this->zone, $this->assignee, days: 40, returnedDaysAgo: 3);
    $returned->assignments()->update(['campaign_id' => $alpha->id]);

    $inAlpha = territoryAssignedSince($this->zone, $this->assignee, days: 5);
    $inAlpha->currentAssignment->update(['campaign_id' => $alpha->id]);

    // Territories assigned outside of any campaign are listed under "no campaign" too
    territoryAssignedSince($this->zone, $this->assignee, days: 5);
    $unassigned = territoryAssignedSince($this->zone, $this->assignee);
    $unassigned->update(['name' => 'Via Roma']);

    $noCampaign = __('filament-resources.territory.filter_no_campaign');

    Livewire::test(ListTerritories::class)
        ->set('tableGrouping', 'currentAssignment.campaign.name')
        ->assertSee('Alpha (1)')
        ->assertSee("{$noCampaign} (3)")
        ->searchTable('Roma')
        ->assertDontSee('Alpha (')
        ->assertSee("{$noCampaign} (1)");
});

test('campaign groups are sorted by campaign name, with unassigned territories last', function () {
    $beta = Campaign::factory()->create(['name' => 'Beta']);
    $alpha = Campaign::factory()->create(['name' => 'Alpha']);

    $unassigned = territoryAssignedSince($this->zone, $this->assignee);
    $inBeta = territoryAssignedSince($this->zone, $this->assignee, days: 5);
    $inAlpha = territoryAssignedSince($this->zone, $this->assignee, days: 5);

    $inBeta->currentAssignment->update(['campaign_id' => $beta->id]);
    $inAlpha->currentAssignment->update(['campaign_id' => $alpha->id]);

    Livewire::test(ListTerritories::class)
        ->set('tableGrouping', 'currentAssignment.campaign.name')
        ->assertCanSeeTableRecords([$inAlpha, $inBeta, $unassigned], inOrder: true)
        ->set('tableGrouping', 'currentAssignment.campaign.name:desc')
        ->assertCanSeeTableRecords([$unassigned, $inBeta, $inAlpha], inOrder: true);
});

test('territories grouped by campaign can be searched', function (Closure $search) {
    $alpha = Campaign::factory()->create(['name' => 'Alpha']);
    $beta = Campaign::factory()->create(['name' => 'Beta']);

    $romaUnassigned = territoryAssignedSince($this->zone, $this->assignee);
    $romaInBeta = territoryAssignedSince($this->zone, $this->assignee, days: 5);
    $romaInAlpha = territoryAssignedSince($this->zone, $this->assignee, days: 5);
    $milanoInAlpha = territoryAssignedSince($this->zone, $this->assignee, days: 5);

    $romaUnassigned->update(['name' => 'Via Roma 1']);
    $romaInBeta->update(['name' => 'Via Roma 2']);
    $romaInAlpha->update(['name' => 'Via Roma 3']);
    $milanoInAlpha->update(['name' => 'Via Milano']);

    $romaInBeta->currentAssignment->update(['campaign_id' => $beta->id]);
    $romaInAlpha->currentAssignment->update(['campaign_id' => $alpha->id]);
    $milanoInAlpha->currentAssignment->update(['campaign_id' => $alpha->id]);

    $table = Livewire::test(ListTerritories::class)
        ->set('tableGrouping', 'currentAssignment.campaign.name');

    $search($table, 'Roma')
        ->assertCanSeeTableRecords([$romaInAlpha, $romaInBeta, $romaUnassigned], inOrder: true)
        ->assertCanNotSeeTableRecords([$milanoInAlpha])
        ->set('tableGrouping', 'currentAssignment.campaign.name:desc')
        ->assertCanSeeTableRecords([$romaUnassigned, $romaInBeta, $romaInAlpha], inOrder: true)
        ->assertCanNotSeeTableRecords([$milanoInAlpha]);
})->with([
    'with the table search' => [fn (Testable $table, string $search): Testable => $table->searchTable($search)],
    'with the name column search' => [fn (Testable $table, string $search): Testable => $table->searchTableColumns(['name' => $search])],
]);

test('the number of queries does not grow with the number of territories', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListTerritories::class);

        return count(DB::getQueryLog());
    };

    foreach (range(1, 2) as $i) {
        territoryAssignedSince($this->zone, $this->assignee, days: 40, returnedDaysAgo: 3);
    }

    // Warm up the caches loaded once per request (e.g. permissions)
    $countQueries();

    $queriesWithFewTerritories = $countQueries();

    foreach (range(1, 8) as $i) {
        territoryAssignedSince($this->zone, $this->assignee, days: 40, returnedDaysAgo: 3);
    }
    $queriesWithManyTerritories = $countQueries();

    expect($queriesWithManyTerritories)->toBe($queriesWithFewTerritories);
});

test('the group counts take one query whatever the number of groups', function (string $grouping) {
    $countQueries = function () use ($grouping): int {
        $table = Livewire::test(ListTerritories::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $table->set('tableGrouping', $grouping);

        return count(DB::getQueryLog());
    };

    $addGroup = function (): void {
        $territory = territoryAssignedSince(TerritoryZone::factory()->create(), $this->assignee, days: 5);
        $territory->currentAssignment->update(['campaign_id' => Campaign::factory()->create()->id]);
    };

    $addGroup();
    $countQueries();
    $queriesWithFewGroups = $countQueries();

    foreach (range(1, 4) as $i) {
        $addGroup();
    }

    expect($countQueries())->toBe($queriesWithFewGroups);
})->with(['territoryZone.name', 'currentAssignment.campaign.name']);

test('territories of soft-deleted zones are hidden', function () {
    $south = TerritoryZone::factory()->create(['name' => 'South']);
    $visibleTerritory = territoryAssignedSince($this->zone, $this->assignee);
    territoryAssignedSince($south, $this->assignee);

    // Deleting a zone that still has territories is refused (see TerritoryZoneObserver),
    // but zones deleted before that, with their territories, can still exist
    $south->deleteQuietly();

    expect(Territory::pluck('id')->all())->toBe([$visibleTerritory->id]);
});
