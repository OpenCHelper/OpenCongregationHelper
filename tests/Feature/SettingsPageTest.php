<?php

use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Enums\CampaignStatus;
use App\Enums\NavigationGroup;
use App\Events\StaleAssignmentDetected;
use App\Filament\Clusters\Settings\Pages\ManageCampaignSettings;
use App\Filament\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Filament\Clusters\Settings\Pages\ManageNotificationSettings;
use App\Filament\Clusters\Settings\Pages\ManageTerritorySettings;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Jobs\DetectStaleAssignments;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Settings\CampaignSettings;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use App\Settings\TerritorySettings;
use App\Support\SettingsOverrides;
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
    'notifications' => ManageNotificationSettings::class,
]);

test('other users cannot open the settings pages', function (string $page) {
    $this->actingAs(User::factory()->create(['can_login' => true]));

    Livewire::test($page)->assertForbidden();
})->with([
    'general' => ManageGeneralSettings::class,
    'territories' => ManageTerritorySettings::class,
    'campaigns' => ManageCampaignSettings::class,
    'notifications' => ManageNotificationSettings::class,
]);

test('the settings are listed in the administration navigation group', function () {
    expect(SettingsCluster::getNavigationGroup())->toBe(NavigationGroup::Administration)
        ->and(ManageGeneralSettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageTerritorySettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageCampaignSettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageNotificationSettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageGeneralSettings::getUrl())->toEndWith('/settings/general');
});

test('the settings never changed show the values of the environment', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->assertSchemaStateSet([
            'name' => config('app.name'),
            'locale' => config('app.locale'),
            'timezone' => config('app.timezone'),
        ]);

    Livewire::test(ManageTerritorySettings::class)
        ->assertSchemaStateSet([
            'map_default_center' => implode(', ', config('filament-leaflet.default_map_center')),
            'shared_pii' => config('app.enable_shared_pii'),
        ]);

    expect(storedSettings('general'))->toMatchArray(['locale' => null, 'name' => null, 'timezone' => null]);
});

test('the general settings are stored and applied', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm([
            'name' => 'Territories of Springfield',
            'locale' => 'es',
            'timezone' => 'Europe/Rome',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(storedSettings('general'))->toMatchArray([
        'name' => 'Territories of Springfield',
        'locale' => 'es',
        'timezone' => 'Europe/Rome',
    ])
        ->and(config('app.name'))->toBe('Territories of Springfield')
        ->and(config('app.timezone'))->toBe('Europe/Rome')
        ->and(date_default_timezone_get())->toBe('Europe/Rome');
});

test('the territory settings are stored and applied', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm([
            'map_default_center' => ' 39.8017 , -89.6436 ',
            'shared_pii' => ! config('app.enable_shared_pii'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $sharedPii = ! app(SettingsOverrides::class)->default(TerritorySettings::class, 'shared_pii');

    expect(storedSettings('territories'))->toMatchArray([
        'map_default_center' => [39.8017, -89.6436],
        'shared_pii' => $sharedPii,
    ])
        ->and(config('filament-leaflet.default_map_center'))->toBe([39.8017, -89.6436])
        ->and(config('app.enable_shared_pii'))->toBe($sharedPii);
});

test('the settings set back to their default follow the environment again', function () {
    actingAsSuperAdmin();

    $settings = app(GeneralSettings::class);
    $settings->name = 'Territories of Springfield';
    $settings->timezone = 'Europe/Rome';
    $settings->save();

    $default = app(SettingsOverrides::class)->default(GeneralSettings::class, 'name');

    Livewire::test(ManageGeneralSettings::class)
        ->assertSchemaStateSet(['name' => 'Territories of Springfield', 'timezone' => 'Europe/Rome'])
        ->fillForm(['name' => $default])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSettings('general'))->toMatchArray(['name' => null, 'timezone' => 'Europe/Rome'])
        ->and(config('app.name'))->toBe($default);
});

test('the language of the administrator is not saved as the default language', function () {
    actingAsSuperAdmin();

    $default = app(SettingsOverrides::class)->default(GeneralSettings::class, 'locale');
    $otherLocale = collect(array_keys(config('app.available_locales')))->first(fn (string $locale): bool => $locale !== $default);

    // As done by `SwitchLanguageLocale` for a user who chose another language, which changes `app.locale` too
    app()->setLocale($otherLocale);

    Livewire::test(ManageGeneralSettings::class)
        ->assertSchemaStateSet(['locale' => $default])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSettings('general')['locale'])->toBeNull()
        ->and(app()->getLocale())->toBe($otherLocale);
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

test('the timezone must be a valid timezone', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['timezone' => 'Mars/Olympus_Mons'])
        ->call('save')
        ->assertHasFormErrors(['timezone']);
});

test('the stored settings are applied over the configuration on boot', function () {
    $settings = app(GeneralSettings::class);
    $settings->timezone = 'America/Chicago';
    $settings->locale = 'es';
    $settings->save();

    $default = config('app.timezone');

    app(SettingsOverrides::class)->apply();

    expect(config('app.timezone'))->toBe('America/Chicago')
        ->and(date_default_timezone_get())->toBe('America/Chicago')
        ->and(app()->getLocale())->toBe('es')
        ->and(app(SettingsOverrides::class)->default(GeneralSettings::class, 'timezone'))->toBe($default);
});

test('the settings are ignored before they are migrated', function () {
    DB::table('settings')->delete();
    app()->forgetScopedInstances();

    $overrides = new SettingsOverrides;
    $overrides->apply();

    expect(config('app.timezone'))->toBe($overrides->default(GeneralSettings::class, 'timezone'));
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

test('without a logo and a primary colour the panel shows the name, in the default colour', function () {
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
            'very_stale_after_days' => 45,
            'extremely_stale_after_days' => 60,
            'dead_after_days' => 75,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(array_keys((new DetectStaleAssignments)->getStaleLevels()))->toBe([30, 45, 60, 75]);

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
            'very_stale_after_days' => 60,
            'extremely_stale_after_days' => 50,
            'dead_after_days' => 150,
        ])
        ->call('save')
        ->assertHasFormErrors(['very_stale_after_days', 'extremely_stale_after_days']);

    expect(app(TerritorySettings::class)->very_stale_after_days)->toBe(90);
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
        ->and($task->timezone)->toBe('Europe/Rome');
});

test('the stale assignments can be checked only on the hour', function () {
    actingAsSuperAdmin();

    Livewire::test(ManageTerritorySettings::class)
        ->fillForm(['stale_check_time' => '18:30'])
        ->call('save')
        ->assertHasFormErrors(['stale_check_time']);
});

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

test('the sender name of the emails follows the environment until changed', function () {
    actingAsSuperAdmin();

    $default = config('mail.from.name');

    Livewire::test(ManageNotificationSettings::class)
        ->assertSchemaStateSet(['mail_from_name' => $default])
        ->fillForm(['mail_from_name' => 'Territories of Springfield'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(NotificationSettings::class)->mail_from_name)->toBe('Territories of Springfield')
        ->and(config('mail.from.name'))->toBe('Territories of Springfield');

    Livewire::test(ManageNotificationSettings::class)
        ->fillForm(['mail_from_name' => $default])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(NotificationSettings::class)->mail_from_name)->toBeNull()
        ->and(config('mail.from.name'))->toBe($default);
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
            __('settings.fields.very_stale_after_days.label'),
            __('settings.fields.extremely_stale_after_days.label'),
            __('settings.fields.dead_after_days.label'),
        ])
        ->assertSeeHtml('fi-settings-stages-bar');
});
