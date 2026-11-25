<?php

use App\Filament\Resources\Campaigns\Widgets\CampaignOverview;
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

test('the campaign overview renders when there are no territories', function () {
    $campaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->addDays(10),
    ]);

    Livewire::test(CampaignOverview::class, ['record' => $campaign])
        ->assertOk()
        ->assertSee('0%');
});

test('territories assigned more than once in a campaign are counted once', function () {
    $campaign = Campaign::factory()->create(['valid_until' => null]);
    $zone = TerritoryZone::factory()->create();
    $assignedTerritory = Territory::factory()->for($zone, 'territoryZone')->create();
    Territory::factory()->for($zone, 'territoryZone')->create();

    // Past assignments of the same territory (only one assignment can be in progress at a time)
    TerritoryAssignment::factory()->count(3)->create([
        'territory_id' => $assignedTerritory->id,
        'assignee_id' => 1,
        'campaign_id' => $campaign->id,
        'ended_at' => now(),
    ]);

    Livewire::test(CampaignOverview::class, ['record' => $campaign])
        ->assertSee('50%')
        ->assertDontSee('150%');
});

test('soft-deleted territories are left out of the completion', function () {
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

    Livewire::test(CampaignOverview::class, ['record' => $campaign])
        ->assertSee('100%')
        ->assertDontSee('200%');
});

test('no warning is shown at the beginning of a campaign', function () {
    $campaign = Campaign::factory()->create([
        'valid_from' => now()->subDay(),
        'valid_until' => now()->addDays(99),
    ]);
    Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    Livewire::test(CampaignOverview::class, ['record' => $campaign])
        ->assertDontSee(__('filament-resources.campaign.widget_warning_25_percent', ['percentage' => 0]))
        ->assertSee(__('filament-resources.campaign.widget_days_remaining', ['days' => 99]));
});

test('a warning is shown when little time remains and few territories are assigned', function () {
    $campaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(90),
        'valid_until' => now()->addDays(10),
    ]);
    Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    Livewire::test(CampaignOverview::class, ['record' => $campaign])
        ->assertSee(__('filament-resources.campaign.widget_warning_25_percent', ['percentage' => 0]));
});
