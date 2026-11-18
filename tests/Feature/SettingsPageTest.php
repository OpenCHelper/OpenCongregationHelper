<?php

use App\Enums\NavigationGroup;
use App\Filament\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Filament\Clusters\Settings\Pages\ManageTerritorySettings;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\TerritorySettings;
use App\Support\SettingsOverrides;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
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
})->with(['general' => ManageGeneralSettings::class, 'territories' => ManageTerritorySettings::class]);

test('other users cannot open the settings pages', function (string $page) {
    $this->actingAs(User::factory()->create(['can_login' => true]));

    Livewire::test($page)->assertForbidden();
})->with(['general' => ManageGeneralSettings::class, 'territories' => ManageTerritorySettings::class]);

test('the settings are listed in the administration navigation group', function () {
    expect(SettingsCluster::getNavigationGroup())->toBe(NavigationGroup::Administration)
        ->and(ManageGeneralSettings::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageTerritorySettings::getCluster())->toBe(SettingsCluster::class)
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

    expect(storedSettings('general'))->toBe(['locale' => null, 'name' => null, 'timezone' => null]);
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

    expect(storedSettings('general'))->toEqual([
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

    expect(storedSettings('territories'))->toEqual([
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
