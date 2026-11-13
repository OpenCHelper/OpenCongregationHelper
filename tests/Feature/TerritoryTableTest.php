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

test('territories of soft-deleted zones are hidden', function () {
    $south = TerritoryZone::factory()->create(['name' => 'South']);
    $visibleTerritory = territoryAssignedSince($this->zone, $this->assignee);
    territoryAssignedSince($south, $this->assignee);

    $south->delete();

    expect(Territory::pluck('id')->all())->toBe([$visibleTerritory->id]);
});
