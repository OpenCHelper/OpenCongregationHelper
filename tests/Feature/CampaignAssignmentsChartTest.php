<?php

use App\Filament\Resources\Campaigns\Widgets\CampaignAssignmentsChart;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->actingAs(User::factory()->create(['id' => 1]));
});

/**
 * @return array{0: int, 1: int, 2: int}
 */
function campaignChartData(Campaign $campaign): array
{
    $chart = Livewire::test(CampaignAssignmentsChart::class, ['record' => $campaign])->instance();

    return (fn () => $this->getData())->call($chart)['datasets'][0]['data'];
}

test('the chart counts every territory once, in a single slice', function () {
    $campaign = Campaign::factory()->create(['valid_until' => null]);
    $zone = TerritoryZone::factory()->create();
    [$reassigned, $completed] = Territory::factory()->count(2)->for($zone, 'territoryZone')->create();
    Territory::factory()->count(2)->for($zone, 'territoryZone')->create();
    $assignment = ['assignee_id' => 1, 'campaign_id' => $campaign->id];

    // Returned and re-assigned in the same campaign
    TerritoryAssignment::factory()->create([...$assignment, 'territory_id' => $reassigned->id, 'ended_at' => now()]);
    TerritoryAssignment::factory()->create([...$assignment, 'territory_id' => $reassigned->id, 'ended_at' => null]);
    // Worked twice in the same campaign
    TerritoryAssignment::factory()->count(2)->create([...$assignment, 'territory_id' => $completed->id, 'ended_at' => now()]);

    // To be assigned, in progress, completed
    expect(campaignChartData($campaign))->toBe([2, 1, 1]);
});

test('the chart ignores assignments of other campaigns', function () {
    $campaign = Campaign::factory()->create(['valid_until' => null]);
    $territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => 1,
        'campaign_id' => Campaign::factory()->create(['valid_until' => null])->id,
        'ended_at' => null,
    ]);

    expect(campaignChartData($campaign))->toBe([1, 0, 0]);
});

test('each slice of the assignment status chart has its own color', function () {
    $chart = Livewire::test(CampaignAssignmentsChart::class, ['record' => Campaign::factory()->create()])
        ->assertOk()
        ->instance();

    $dataset = (fn (): array => $this->getData())->call($chart)['datasets'][0];

    expect($dataset['backgroundColor'])->toHaveCount(3)
        ->and(array_unique($dataset['backgroundColor']))->toHaveCount(3)
        ->and($dataset['backgroundColor'])->toHaveCount(count($dataset['data']));
});

test('soft-deleted territories are left out of every slice', function () {
    $campaign = Campaign::factory()->create(['valid_until' => null]);
    [$deleted, $worked] = Territory::factory()->count(2)->for(TerritoryZone::factory(), 'territoryZone')->create();

    foreach ([$deleted, $worked] as $territory) {
        TerritoryAssignment::factory()->create([
            'territory_id' => $territory->id,
            'assignee_id' => 1,
            'campaign_id' => $campaign->id,
            'ended_at' => now(),
        ]);
    }
    $deleted->delete();

    expect(campaignChartData($campaign))->toBe([0, 0, 1]);
});
