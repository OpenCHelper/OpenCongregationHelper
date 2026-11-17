<?php

use App\Filament\Pages\EditProfile;
use App\Models\User;
use App\Support\AppVersions;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

test('the user menu has the same items in every language', function (string $locale) {
    app()->setLocale($locale);

    // Filament adds a header with the name of the user when no item is named `profile`
    expect(array_keys(Filament::getPanel('app')->getUserMenuItems()))
        ->toBe(['profile', 'uiPreferences', 'versionInfo', 'logout']);
})->with(['en', 'it', 'es']);

test('the UI preferences are picked from the user menu, the language from the top bar', function () {
    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => now()]))
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertDontSee('fi-dropdown-header', escape: false)
        ->assertSeeInOrder([
            'fi-ls-trigger',
            'fi-user-menu',
            __('filament-resources.user_profile.menu_label'),
            "open-modal', { id: 'ui-switcher-modal' }",
            __('filament-ui-switcher::filament-ui-switcher.modal.heading'),
        ], escape: false)
        // The panel opened by the menu item, whose own button is hidden by the theme
        ->assertSee('<div class="app-ui-preferences">', escape: false)
        ->assertSee('id="ui-switcher-modal"', escape: false);
});

test('the versions are shown from the user menu', function () {
    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => now()]))
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'fi-user-menu',
            __('filament-resources.version_info.menu_label'),
        ], escape: false)
        ->assertSee('versionInfo', escape: false);
});

test('the version modal lists the version of the app', function () {
    config(['app.version' => '9.8.7-test']);

    expect(view('filament.version-info', ['versions' => AppVersions::all()])->render())
        ->toContain('App')
        ->toContain('9.8.7-test');
});

test('the versions of the stack are listed only outside production', function (string $environment, array $names) {
    app()->detectEnvironment(fn (): string => $environment);

    expect(array_keys(AppVersions::all()))->toBe($names);
})->with([
    'local' => ['local', ['Laravel', 'Filament', 'PHP', 'App']],
    'production' => ['production', ['App']],
]);
