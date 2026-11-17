<?php

use App\Filament\Pages\EditProfile;
use App\Models\User;
use App\Providers\AppVersionProvider;
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

    expect(view('filament.version-info', ['versions' => AppVersions::all(), 'build' => AppVersions::build()])->render())
        ->toContain('App')
        ->toContain('9.8.7-test');
});

test('the version modal shows the branch, commit and date of the build', function () {
    file_put_contents(base_path('test-git-head'), 'ref: refs/heads/release-branch');
    file_put_contents(base_path('test-git-commit'), 'abcdef1234567890abcdef1234567890abcdef12');
    touch(base_path('test-git-commit'), strtotime('2026-01-02 03:04:05 UTC'));
    config([
        'app.timezone' => 'UTC',
        'app.version_info.branch_file' => 'test-git-head',
        'app.version_info.commit_file' => 'test-git-commit',
    ]);

    try {
        $build = AppVersions::build();
        $html = view('filament.version-info', ['versions' => AppVersions::all(), 'build' => $build])->render();
    } finally {
        unlink(base_path('test-git-head'));
        unlink(base_path('test-git-commit'));
    }

    expect($build)->toBe([
        'branch' => 'release-branch',
        'commit' => 'abcdef1234567890abcdef1234567890abcdef12',
        'date' => '2026-01-02 03:04:05',
    ]);

    expect($html)
        ->toContain(__('filament-resources.version_info.build'))
        ->toContain('release-branch')
        ->toMatch('/title="abcdef1234567890abcdef1234567890abcdef12"\s*>abcdef1</')
        ->toContain('2026-01-02 03:04:05')
        // The version of the app no longer repeats the details of the build
        ->not->toContain('release-branch#');
});

test('the build details are the ones of the version of the app', function () {
    file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');
    file_put_contents(base_path('test-git-commit'), 'abcdef1234567890abcdef1234567890abcdef12');
    touch(base_path('test-git-commit'), strtotime('2026-01-02 03:04:05 UTC'));
    config([
        'app.version' => '1.0.0',
        'app.version_info.branch_file' => 'test-git-head',
        'app.version_info.commit_file' => 'test-git-commit',
    ]);

    try {
        $build = AppVersions::build();
        $version = (new AppVersionProvider)->getVersion();
    } finally {
        unlink(base_path('test-git-head'));
        unlink(base_path('test-git-commit'));
    }

    expect($version)->toBe('1.0.0 ('.$build['branch'].'#'.substr($build['commit'], 0, 7).' @ '.$build['date'].')');
});

test('unknown build details are shown as unknown', function () {
    config(['app.version_info.branch_file' => 'non-existent-file']);

    $html = view('filament.version-info', ['versions' => AppVersions::all(), 'build' => AppVersions::build()])->render();

    expect(substr_count($html, __('filament-resources.version_info.unknown')))->toBe(3);
});

test('the versions of the stack are listed only outside production', function (string $environment, array $names) {
    app()->detectEnvironment(fn (): string => $environment);

    expect(array_keys(AppVersions::all()))->toBe($names);
})->with([
    'local' => ['local', ['Laravel', 'Filament', 'PHP', 'App']],
    'production' => ['production', ['App']],
]);
