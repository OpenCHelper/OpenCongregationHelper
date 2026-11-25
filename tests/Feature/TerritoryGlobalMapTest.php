<?php

use App\Enums\TerritoryMapCategory;
use App\Enums\TerritoryMapMode;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Widgets\TerritoryGlobalMap;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Settings\TerritorySettings;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $viewer = User::factory()->create();
    foreach (['View:TerritoryGlobalMap', 'ViewAny:Territory'] as $permission) {
        $viewer->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($viewer);

    $this->assignee = User::factory()->create();
    $this->zone = TerritoryZone::factory()->create(['name' => 'North', 'color' => '#ff0000']);
});

/**
 * Create a residential territory with a map in the given zone.
 */
function territoryOnGlobalMap(TerritoryZone $zone, string $name): Territory
{
    return Territory::factory()->withMap()->for($zone, 'territoryZone')->create(['name' => $name]);
}

/**
 * Assign a territory since the given number of days, optionally returned and linked to a campaign.
 */
function assignTerritoryOnGlobalMap(Territory $territory, User $assignee, int $startedDaysAgo, ?int $returnedDaysAgo = null, ?Campaign $campaign = null): TerritoryAssignment
{
    return TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $assignee->id,
        'campaign_id' => $campaign?->id,
        'started_at' => now()->subDays($startedDaysAgo),
        'ended_at' => $returnedDaysAgo !== null ? now()->subDays($returnedDaysAgo) : null,
    ]);
}

/**
 * Get the fill color of the polygon of each territory shown on the map, by territory name.
 *
 * @return array<string, string>
 */
function globalMapFillColors(Testable $component): array
{
    return collect($component->instance()->getMapData()['layersData'])
        ->mapWithKeys(fn (array $polygon): array => [
            html_entity_decode($polygon['tooltip']['content']) => $polygon['options']['fillColor'],
        ])
        ->all();
}

/**
 * Get the popup content of the polygon of each territory shown on the map, by territory name.
 *
 * @return array<string, string>
 */
function globalMapPopups(Testable $component): array
{
    return collect($component->instance()->getMapData()['layersData'])
        ->mapWithKeys(fn (array $polygon): array => [
            html_entity_decode($polygon['tooltip']['content']) => $polygon['popup']['content'] ?? '',
        ])
        ->all();
}

/**
 * Get the number of territories of each legend entry, by label.
 *
 * @return array<string, int>
 */
function globalMapLegendCounts(Testable $component): array
{
    return collect($component->instance()->getLegend())
        ->mapWithKeys(fn (array $legendItem): array => [$legendItem['label'] => $legendItem['count']])
        ->all();
}

function globalMapCategoryColor(TerritoryMapCategory $category): string
{
    return Color::convertToRgb($category->getColor()[500]);
}

test('territories are colored by zone by default', function () {
    $southZone = TerritoryZone::factory()->create(['name' => 'South', 'color' => null]);

    territoryOnGlobalMap($this->zone, 'North 1');
    territoryOnGlobalMap($this->zone, 'North 2');
    territoryOnGlobalMap($southZone, 'South 1');

    // Not shown: commercial territories and territories without a map
    Territory::factory()->withMap()->isCommercial()->for($this->zone, 'territoryZone')->create(['name' => 'Shop']);
    Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'No map']);

    $component = Livewire::test(TerritoryGlobalMap::class)
        ->assertOk()
        ->assertSet('mapMode', TerritoryMapMode::ZONE)
        ->assertSee(__('filament-widgets.territory_global_map.heading'));

    expect(globalMapFillColors($component))->toBe([
        'North 1' => 'rgb(255, 0, 0)',
        'North 2' => 'rgb(255, 0, 0)',
        // Zones without a color use the default one
        'South 1' => Color::convertToRgb(Color::Blue[500]),
    ])
        ->and(globalMapLegendCounts($component))->toBe([
            'North' => 2,
            'South' => 1,
        ]);
});

test('territories are colored by their current status', function () {
    territoryOnGlobalMap($this->zone, 'Never assigned');
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'Returned'), $this->assignee, startedDaysAgo: 30, returnedDaysAgo: 10);
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'Assigned'), $this->assignee, startedDaysAgo: 5);

    $component = Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::STATUS->value)
        ->assertSee(TerritoryMapCategory::AVAILABLE->getLabel());

    expect(globalMapFillColors($component))->toBe([
        'Assigned' => globalMapCategoryColor(TerritoryMapCategory::NOT_AVAILABLE),
        'Never assigned' => globalMapCategoryColor(TerritoryMapCategory::AVAILABLE),
        'Returned' => globalMapCategoryColor(TerritoryMapCategory::AVAILABLE),
    ])
        ->and(globalMapLegendCounts($component))->toBe([
            TerritoryMapCategory::AVAILABLE->getLabel() => 2,
            TerritoryMapCategory::NOT_AVAILABLE->getLabel() => 1,
        ]);
});

test('territories are colored by how many times they were assigned in the last 12 months', function () {
    territoryOnGlobalMap($this->zone, 'Never assigned');
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'Assigned long ago'), $this->assignee, startedDaysAgo: 400, returnedDaysAgo: 300);
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'Assigned once'), $this->assignee, startedDaysAgo: 100, returnedDaysAgo: 50);

    $assignedTwice = territoryOnGlobalMap($this->zone, 'Assigned twice');
    assignTerritoryOnGlobalMap($assignedTwice, $this->assignee, startedDaysAgo: 200, returnedDaysAgo: 150);
    assignTerritoryOnGlobalMap($assignedTwice, $this->assignee, startedDaysAgo: 30);

    $component = Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::FREQUENCY->value);

    expect(globalMapFillColors($component))->toBe([
        'Assigned long ago' => globalMapCategoryColor(TerritoryMapCategory::LOW_FREQUENCY),
        'Assigned once' => globalMapCategoryColor(TerritoryMapCategory::MEDIUM_FREQUENCY),
        'Assigned twice' => globalMapCategoryColor(TerritoryMapCategory::HIGH_FREQUENCY),
        'Never assigned' => globalMapCategoryColor(TerritoryMapCategory::LOW_FREQUENCY),
    ])
        ->and(globalMapLegendCounts($component))->toBe([
            TerritoryMapCategory::LOW_FREQUENCY->getLabel() => 2,
            TerritoryMapCategory::MEDIUM_FREQUENCY->getLabel() => 1,
            TerritoryMapCategory::HIGH_FREQUENCY->getLabel() => 1,
        ])
        ->and(globalMapPopups($component)['Assigned twice'])
        ->toContain(e(__('filament-widgets.territory_global_map.popup_recent_assignments', [
            'period' => TerritoryMapCategory::getFrequencyPeriod(),
            'count' => 2,
        ])));
});

test('the period and the thresholds of the assignment frequency are the ones of the settings', function () {
    app()->setLocale('en');
    $settings = app(TerritorySettings::class);
    $settings->frequency_period_months = 6;
    $settings->medium_frequency_min_assignments = 2;
    $settings->high_frequency_min_assignments = 3;
    $settings->save();

    $assignedTwiceThisYear = territoryOnGlobalMap($this->zone, 'Assigned twice this year');
    assignTerritoryOnGlobalMap($assignedTwiceThisYear, $this->assignee, startedDaysAgo: 250, returnedDaysAgo: 220);
    assignTerritoryOnGlobalMap($assignedTwiceThisYear, $this->assignee, startedDaysAgo: 30);

    $assignedTwiceRecently = territoryOnGlobalMap($this->zone, 'Assigned twice recently');
    assignTerritoryOnGlobalMap($assignedTwiceRecently, $this->assignee, startedDaysAgo: 120, returnedDaysAgo: 90);
    assignTerritoryOnGlobalMap($assignedTwiceRecently, $this->assignee, startedDaysAgo: 30);

    $component = Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::FREQUENCY->value);

    // Only the assignments of the last 6 months are counted
    expect(globalMapFillColors($component))->toBe([
        'Assigned twice recently' => globalMapCategoryColor(TerritoryMapCategory::MEDIUM_FREQUENCY),
        'Assigned twice this year' => globalMapCategoryColor(TerritoryMapCategory::LOW_FREQUENCY),
    ])
        ->and(TerritoryMapCategory::LOW_FREQUENCY->getLabel())->toBe('Low (fewer than 2 assignments in the last 6 months)')
        ->and(TerritoryMapCategory::MEDIUM_FREQUENCY->getLabel())->toBe('Medium (2 assignments)')
        ->and(TerritoryMapCategory::HIGH_FREQUENCY->getLabel())->toBe('Frequent (3 or more assignments)')
        ->and(globalMapPopups($component)['Assigned twice recently'])->toContain('Assignments in the last 6 months: 2');
});

test('the frequency labels describe the range of the medium frequency and a single month', function () {
    app()->setLocale('en');
    $settings = app(TerritorySettings::class);
    $settings->frequency_period_months = 1;
    $settings->medium_frequency_min_assignments = 1;
    $settings->high_frequency_min_assignments = 4;
    $settings->save();

    expect(TerritoryMapCategory::LOW_FREQUENCY->getLabel())->toBe('Low (no assignment in the last month)')
        ->and(TerritoryMapCategory::MEDIUM_FREQUENCY->getLabel())->toBe('Medium (1 to 3 assignments)');
});

test('the frequency labels are unchanged with the default settings', function () {
    app()->setLocale('en');
    expect(TerritoryMapCategory::LOW_FREQUENCY->getLabel())->toBe('Low (no assignment in the last 12 months)')
        ->and(TerritoryMapCategory::MEDIUM_FREQUENCY->getLabel())->toBe('Medium (1 assignment)')
        ->and(TerritoryMapCategory::HIGH_FREQUENCY->getLabel())->toBe('Frequent (2 or more assignments)');
});

test('territories are colored by their progress in the selected campaign', function () {
    $campaign = Campaign::factory()->create(['valid_from' => now()->subDays(60), 'valid_until' => now()->addDays(30)]);
    $otherCampaign = Campaign::factory()->create(['valid_from' => now()->subDays(90), 'valid_until' => null]);

    territoryOnGlobalMap($this->zone, 'To be assigned');
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'Worked for another campaign'), $this->assignee, startedDaysAgo: 50, returnedDaysAgo: 40, campaign: $otherCampaign);
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'Out of the campaign'), $this->assignee, startedDaysAgo: 70);
    assignTerritoryOnGlobalMap(territoryOnGlobalMap($this->zone, 'In progress'), $this->assignee, startedDaysAgo: 20, campaign: $campaign);

    $worked = territoryOnGlobalMap($this->zone, 'Worked');
    assignTerritoryOnGlobalMap($worked, $this->assignee, startedDaysAgo: 40, returnedDaysAgo: 30, campaign: $campaign);
    // Assigned again after being worked for the campaign
    assignTerritoryOnGlobalMap($worked, $this->assignee, startedDaysAgo: 10);

    $component = Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::CAMPAIGN->value)
        ->assertSet('campaignId', $campaign->id)
        ->assertSee($campaign->name);

    expect(globalMapFillColors($component))->toBe([
        'In progress' => globalMapCategoryColor(TerritoryMapCategory::IN_PROGRESS),
        'Out of the campaign' => globalMapCategoryColor(TerritoryMapCategory::OUT_FOR_OTHER_ASSIGNMENT),
        'To be assigned' => globalMapCategoryColor(TerritoryMapCategory::TO_BE_ASSIGNED),
        'Worked' => globalMapCategoryColor(TerritoryMapCategory::WORKED),
        'Worked for another campaign' => globalMapCategoryColor(TerritoryMapCategory::TO_BE_ASSIGNED),
    ])
        ->and(globalMapLegendCounts($component))->toBe([
            TerritoryMapCategory::OUT_FOR_OTHER_ASSIGNMENT->getLabel() => 1,
            TerritoryMapCategory::TO_BE_ASSIGNED->getLabel() => 2,
            TerritoryMapCategory::IN_PROGRESS->getLabel() => 1,
            TerritoryMapCategory::WORKED->getLabel() => 1,
        ]);

    $component->set('campaignId', $otherCampaign->id);

    expect(globalMapFillColors($component))->toMatchArray([
        'Worked' => globalMapCategoryColor(TerritoryMapCategory::OUT_FOR_OTHER_ASSIGNMENT),
        'Worked for another campaign' => globalMapCategoryColor(TerritoryMapCategory::WORKED),
    ]);
});

test('the active campaign started last is shown by default and terminated campaigns cannot be chosen', function () {
    $terminatedCampaign = Campaign::factory()->create(['valid_from' => now()->subDays(60), 'valid_until' => now()->subDay()]);
    $olderActiveCampaign = Campaign::factory()->create(['valid_from' => now()->subDays(30), 'valid_until' => null]);
    $newerActiveCampaign = Campaign::factory()->create(['valid_from' => now()->subDays(5), 'valid_until' => now()->addDays(30)]);
    $upcomingCampaign = Campaign::factory()->create(['valid_from' => now()->addDays(10), 'valid_until' => now()->addDays(40)]);

    $component = Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::CAMPAIGN->value)
        ->assertSet('campaignId', $newerActiveCampaign->id);

    expect(array_keys($component->instance()->getCampaignOptions()))->toBe([
        $upcomingCampaign->id,
        $newerActiveCampaign->id,
        $olderActiveCampaign->id,
    ]);

    $component
        ->set('campaignId', $olderActiveCampaign->id)
        ->assertSet('campaignId', $olderActiveCampaign->id)
        ->set('campaignId', $terminatedCampaign->id)
        ->assertSet('campaignId', $newerActiveCampaign->id);
});

test('the next campaign is shown by default when none is active', function () {
    Campaign::factory()->create(['valid_from' => now()->addDays(20)]);
    $nextCampaign = Campaign::factory()->create(['valid_from' => now()->addDays(10)]);

    Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::CAMPAIGN->value)
        ->assertSet('mapMode', TerritoryMapMode::CAMPAIGN)
        ->assertSet('campaignId', $nextCampaign->id);
});

test('territories cannot be colored by campaign status without a campaign that is not terminated', function () {
    Campaign::factory()->create(['valid_until' => now()->subDay()]);

    $component = Livewire::test(TerritoryGlobalMap::class);

    expect($component->instance()->getMapModeOptions())->not->toHaveKey(TerritoryMapMode::CAMPAIGN->value);

    $component
        ->set('mapMode', TerritoryMapMode::CAMPAIGN->value)
        ->assertSet('mapMode', TerritoryMapMode::ZONE)
        ->assertSet('campaignId', null);
});

test('the selected map mode is remembered for the session', function () {
    Livewire::test(TerritoryGlobalMap::class)
        ->set('mapMode', TerritoryMapMode::FREQUENCY->value);

    Livewire::test(TerritoryGlobalMap::class)
        ->assertSet('mapMode', TerritoryMapMode::FREQUENCY);
});

test('the popup shows the zone and links to the territory only for users who can view it', function () {
    $territory = territoryOnGlobalMap($this->zone, 'North 1');
    $territoryUrl = e(TerritoryResource::getUrl('view', ['record' => $territory]));

    $popup = globalMapPopups(Livewire::test(TerritoryGlobalMap::class))['North 1'];

    expect($popup)
        ->toContain(e(__('filament-widgets.territory_global_map.popup_zone', ['zone' => 'North'])))
        ->not->toContain($territoryUrl);

    auth()->user()->givePermissionTo(Permission::findOrCreate('View:Territory', 'web'));

    expect(globalMapPopups(Livewire::test(TerritoryGlobalMap::class))['North 1'])->toContain($territoryUrl);
});

test('territory and zone names are escaped on the map', function () {
    $zone = TerritoryZone::factory()->create(['name' => '<i>Zone</i>']);
    territoryOnGlobalMap($zone, '<b>Territory</b>');

    $mapData = Livewire::test(TerritoryGlobalMap::class)->instance()->getMapData();

    expect($mapData['layersData'][0]['tooltip']['content'])->toBe('&lt;b&gt;Territory&lt;/b&gt;')
        ->and($mapData['layersData'][0]['popup']['content'])->not->toContain('<i>')
        ->and($mapData['layerGroupsData'][0]['name'])->toBe('&lt;i&gt;Zone&lt;/i&gt;');
});

test('clicking the map or a territory does not send notifications', function () {
    territoryOnGlobalMap($this->zone, 'North 1');

    Livewire::test(TerritoryGlobalMap::class)
        ->call('handleMapClick', 41.9, 12.4)
        ->call('handleLayerClick', 'any-layer')
        ->assertNotNotified();
});
