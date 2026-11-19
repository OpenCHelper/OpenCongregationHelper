<?php

use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Enums\CampaignStatus;
use App\Enums\NavigationGroup;
use App\Events\StaleAssignmentDetected;
use App\Filament\Clusters\Settings\Pages\ManageCampaignSettings;
use App\Filament\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Filament\Clusters\Settings\Pages\ManageTerritorySettings;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Widgets\TerritoryGlobalMap;
use App\Jobs\DetectStaleAssignments;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Settings\CampaignSettings;
use App\Settings\GeneralSettings;
use App\Settings\TerritorySettings;
use App\Support\MapCenter;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Event as ScheduledTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

/**
 * Log in as a super administrator.
 */
function actingAsSuperAdmin(): User
{
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    test()->actingAs($superAdmin);

    return $superAdmin;
}

/**
 * Get the stored payloads of the settings of a group, keyed by name.
 *
 * @return array<string, mixed>
 */
function storedSettings(string $group): array
{
    return DB::table('settings')
        ->where('group', $group)
        ->pluck('payload', 'name')
        ->map(fn (string $payload): mixed => json_decode($payload, true))
        ->all();
}

test('super administrators can open the settings pages', function (string $page) {
    actingAsSuperAdmin();

    Livewire::test($page)->assertOk();
})->with([
    'general' => ManageGeneralSettings::class,
    'territories' => ManageTerritorySettings::class,
    'campaigns' => ManageCampaignSettings::class,
]);

test('other users cannot open the settings pages', function (string $page) {
    $this->actingAs(User::factory()->create(['can_login' => true]));

    Livewire::test($page)->assertForbidden();
})->with([
    'general' => ManageGeneralSettings::class,
    'territories' => ManageTerritorySettings::class,
    'campaigns' => ManageCampaignSettings::class,
]);

test('the settings are listed in the administration navigation group', function () {
    expect(SettingsCluster::getNavigationGroup())->toBe(NavigationGroup::Administration)
        ->and(ManageGeneralSettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageTerritorySettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageCampaignSettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageGeneralSettings::getUrl())->toEndWith('/settings/general');
});

test('the settings never changed show the values of the environment', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->assertSchemaStateSet([
            'map_default_center' => implode(', ', config('filament-leaflet.default_map_center')),
            'shared_pii' => config('app.enable_shared_pii'),
        ]);

    expect(storedSettings('territories'))->toMatchArray(['map_default_center' => null, 'shared_pii' => null]);
});

test('the territory settings are stored and used', function () {
    actingAsSuperAdmin();

    $sharedPii = ! config('app.enable_shared_pii');

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm([
            'map_default_center' => ' 39.8017 , -89.6436 ',
            'shared_pii' => $sharedPii,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSettings('territories'))->toMatchArray([
        'map_default_center' => [39.8017, -89.6436],
        'shared_pii' => $sharedPii,
    ])
        ->and(MapCenter::resolve())->toBe([39.8017, -89.6436])
        ->and(TerritorySettings::value('shared_pii'))->toBe($sharedPii);
});

test('the settings set back to their default follow the environment again', function () {
    actingAsSuperAdmin();

    $settings = app(TerritorySettings::class);
    $settings->map_default_center = [39.8017, -89.6436];
    $settings->save();

    $default = config('filament-leaflet.default_map_center');

    Livewire::test(ManageTerritorySettings::class)
        ->assertSchemaStateSet(['map_default_center' => '39.8017, -89.6436'])
        ->fillForm(['map_default_center' => implode(', ', $default)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSettings('territories')['map_default_center'])->toBeNull()
        ->and(TerritorySettings::value('map_default_center'))->toBe($default);
});

test('the default map centre must be valid coordinates', function (string $coordinates) {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['map_default_center' => $coordinates])
        ->call('save')
        ->assertHasFormErrors(['map_default_center']);

    expect(storedSettings('territories')['map_default_center'])->toBeNull();
})->with([
    'a single number' => '41.15',
    'not numbers' => 'north, west',
    'latitude out of range' => '91, 12',
    'longitude out of range' => '41, 181',
]);

test('the settings follow the environment before they are migrated', function () {
    DB::table('settings')->delete();
    app()->forgetScopedInstances();

    expect(TerritorySettings::value('map_default_center'))->toBe(config('filament-leaflet.default_map_center'))
        ->and(CampaignSettings::value('expiring_days'))->toBe(7);
});

test('the logo and the primary colour are applied to the panel', function () {
    Storage::fake('public');
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
            'primary_color' => '#2563eb',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $logo = app(GeneralSettings::class)->logo;

    Storage::disk('public')->assertExists($logo);

    $panel = Filament::getPanel('app');

    expect($panel->getBrandLogo())->toBe(Storage::disk('public')->url($logo))
        ->and(FilamentUiSwitcherPlugin::getForPanel($panel)->getDefaults()['color'])->toBe('#2563eb');
});

test('without a logo and a primary colour the panel uses its defaults', function () {
    $panel = Filament::getPanel('app');

    expect($panel->getBrandLogo())->toBeNull()
        ->and(FilamentUiSwitcherPlugin::getForPanel($panel)->getDefaults()['color'])->toBe(config('ui-switcher.defaults.color'));
});

test('the replaced logo is deleted', function () {
    Storage::fake('public');
    actingAsSuperAdmin();

    $settings = app(GeneralSettings::class);
    $settings->logo = UploadedFile::fake()->image('old.png')->store('branding', 'public');
    $settings->save();

    $oldLogo = $settings->logo;

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['logo' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk('public')->assertMissing($oldLogo);

    expect(app(GeneralSettings::class)->logo)->toBeNull();
});

test('the primary colour must be a hex colour', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['primary_color' => 'blue'])
        ->call('save')
        ->assertHasFormErrors(['primary_color']);
});

test('the stale assignment levels are used to find the assignments out for too long', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm([
            'stale_after_days' => 30,
            'dead_after_days' => 75,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(array_column((new DetectStaleAssignments)->getStaleLevels(), 'days'))->toBe([30, 75]);

    $assignee = User::factory()->create();
    $zone = TerritoryZone::factory()->create();
    $createAssignment = fn (int $days): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($zone, 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'created_by' => $assignee->id,
        'started_at' => now()->subDays($days),
        'ended_at' => null,
    ]);

    $staleAssignment = $createAssignment(31);
    $createAssignment(29);

    Event::fake([StaleAssignmentDetected::class]);

    (new DetectStaleAssignments)->handle();

    Event::assertDispatchedTimes(StaleAssignmentDetected::class, 1);
    Event::assertDispatched(StaleAssignmentDetected::class, fn (StaleAssignmentDetected $event): bool => $event->assignment->is($staleAssignment));
});

test('the stale assignment levels must grow', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm([
            'stale_after_days' => 60,
            'dead_after_days' => 50,
        ])
        ->call('save')
        ->assertHasFormErrors(['dead_after_days']);

    expect(app(TerritorySettings::class)->dead_after_days)->toBe(150);
});

test('the stale assignments are checked on the chosen day and time', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['stale_check_day' => Schedule::WEDNESDAY, 'stale_check_time' => '18:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    Artisan::all();

    $task = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledTask $task): bool => $task->description === DetectStaleAssignments::class);

    expect($task->expression)->toBe('0 18 * * 3')
        ->and($task->timezone)->toBe(config('app.timezone'));
});

test('equal stale assignment levels are both kept', function () {
    $settings = app(TerritorySettings::class);
    $settings->stale_after_days = 90;
    $settings->dead_after_days = 90;
    $settings->save();

    expect((new DetectStaleAssignments)->getStaleLevels())->toHaveKeys(['stale', 'dead']);
});

test('the stale assignments can be checked on any day and at any time', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['stale_check_day' => Schedule::SATURDAY, 'stale_check_time' => '07:35'])
        ->call('save')
        ->assertHasNoFormErrors();

    Artisan::all();

    $task = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledTask $task): bool => $task->description === DetectStaleAssignments::class);

    expect($task->expression)->toBe('35 7 * * 6');
});

test('the time of the stale assignments check must be a time', function (string $time) {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['stale_check_time' => $time])
        ->call('save')
        ->assertHasFormErrors(['stale_check_time']);
})->with(['not a time' => 'soon', 'out of range' => '25:00']);

test('the campaign thresholds decide when a campaign is expiring', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageCampaignSettings::class)
        ->assertSchemaStateSet(['expiring_days' => 7, 'almost_expired_days' => 3])
        ->fillForm(['expiring_days' => 14, 'almost_expired_days' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(CampaignSettings::class)->expiring_days)->toBe(14)
        ->and(CampaignStatus::fromValidUntil(Carbon::now()->addDays(10)))->toBe(CampaignStatus::IS_EXPIRING)
        ->and(CampaignStatus::fromValidUntil(Carbon::now()->addDays(4)))->toBe(CampaignStatus::ALMOST_EXPIRED)
        ->and(CampaignStatus::fromValidUntil(Carbon::now()->addDays(15)))->toBe(CampaignStatus::ACTIVE);
});

test('a campaign is almost expired fewer days before its end than it is expiring', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageCampaignSettings::class)
        ->fillForm(['expiring_days' => 5, 'almost_expired_days' => 5])
        ->call('save')
        ->assertHasFormErrors(['almost_expired_days']);
});

test('the campaign thresholds are shown in the order of the statuses, with a bar to scale', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageCampaignSettings::class)
        ->assertSeeInOrder([
            __('filament-resources.campaign.status_active'),
            __('filament-resources.campaign.status_is_expiring'),
            __('filament-resources.campaign.status_almost_expired'),
            __('filament-resources.campaign.status_terminated'),
        ])
        ->assertSeeHtml('fi-settings-stages-bar');
});

test('the stale assignment levels are shown in order, with a bar to scale', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->assertSeeInOrder([
            __('settings.stages.in_progress'),
            __('settings.fields.stale_after_days.label'),
            __('settings.fields.dead_after_days.label'),
        ])
        ->assertSeeHtml('fi-settings-stages-bar');
});

test('the address of the Kingdom Hall is stored', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->assertSchemaStateSet(['kingdom_hall_address' => null])
        ->fillForm(['kingdom_hall_address' => "Via Roma 1\n00043 Ciampino (RM)"])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->kingdom_hall_address)->toBe("Via Roma 1\n00043 Ciampino (RM)");

    Livewire::test(ManageGeneralSettings::class)
        ->assertSchemaStateSet(['kingdom_hall_address' => "Via Roma 1\n00043 Ciampino (RM)"]);
});

test('the address of the Kingdom Hall is optional', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['kingdom_hall_address' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->kingdom_hall_address)->toBeNull();
});

test('the global map opens on the default centre of the settings', function () {
    $this->actingAs(User::factory()->create());

    expect(Livewire::test(TerritoryGlobalMap::class)->instance()->getMapData()['defaultCoord'])
        ->toBe(config('filament-leaflet.default_map_center'));

    $settings = app(TerritorySettings::class);
    $settings->map_default_center = [39.8017, -89.6436];
    $settings->save();

    expect(Livewire::test(TerritoryGlobalMap::class)->instance()->getMapData()['defaultCoord'])
        ->toBe([39.8017, -89.6436]);
});

test('several settings read at once fall back on the environment too', function () {
    expect(TerritorySettings::values('map_default_center', 'shared_pii', 'stale_after_days'))->toBe([
        'map_default_center' => config('filament-leaflet.default_map_center'),
        'shared_pii' => config('app.enable_shared_pii'),
        'stale_after_days' => 60,
    ]);
});
