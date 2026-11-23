<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EditProfile;
use App\Http\Controllers\WebManifestController;
use App\Models\User;
use App\Support\ServiceWorker;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

it('serves the web app manifest', function () {
    config(['app.name' => 'Congregation Helper']);

    $response = $this->get(route('web-manifest'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson([
            'name' => 'Congregation Helper',
            'short_name' => 'Congregation Helper',
            'start_url' => '/app',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => WebManifestController::THEME_COLOR,
            'background_color' => WebManifestController::BACKGROUND_COLOR,
        ]);

    $icons = collect($response->json('icons'));

    expect($icons->pluck('sizes'))->toContain('192x192', '512x512')
        ->and($icons->where('purpose', 'maskable'))->not->toBeEmpty();
});

it('only lists icons that exist', function () {
    $icons = $this->get(route('web-manifest'))->json('icons');

    foreach ($icons as $icon) {
        expect(public_path(ltrim(parse_url($icon['src'], PHP_URL_PATH), '/')))->toBeFile();
    }
});

it('translates the manifest description', function () {
    app()->setLocale('it');

    $this->get(route('web-manifest'))
        ->assertJson([
            'description' => __('welcome.meta_description', locale: 'it'),
            'lang' => 'it',
        ]);
});

it('serves the offline page', function () {
    $this->get(route('offline'))
        ->assertOk()
        ->assertSee(__('pwa.offline.title'))
        ->assertSee(__('pwa.offline.retry'));
});

it('ships the service worker and the icons it caches', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(file_get_contents(public_path('sw.js')))->toContain("const OFFLINE_URL = '/offline'")
        ->and(public_path('icons/apple-touch-icon.png'))->toBeFile();
});

it('registers the service worker with its version, to replace its cache on new versions', function () {
    config(['app.version' => 'v2.0.0-beta1']);

    $version = ServiceWorker::version();

    expect($version)->toStartWith('v2.0.0-beta1+');

    $this->get('/')
        ->assertOk()
        ->assertSee("navigator.serviceWorker.register('\/sw.js?version=".rawurlencode($version)."')", escape: false);

    expect(file_get_contents(public_path('sw.js')))
        ->toContain('const CACHE = `opencongregationhelper-${VERSION}`')
        ->toContain("searchParams.get('version')");
});

it('changes the fingerprint of the cached files when one of them changes', function () {
    $file = tempnam(sys_get_temp_dir(), 'pwa');
    file_put_contents($file, 'first content');

    try {
        $fingerprint = ServiceWorker::fingerprint([$file]);

        expect(ServiceWorker::fingerprint([$file]))->toBe($fingerprint);

        file_put_contents($file, 'second content');

        expect(ServiceWorker::fingerprint([$file]))->not->toBe($fingerprint);
    } finally {
        unlink($file);
    }
});

it('links the manifest and registers the service worker', function (string $uri) {
    $this->followingRedirects()
        ->get($uri)
        ->assertOk()
        ->assertSee('<link rel="manifest" href="'.route('web-manifest').'">', escape: false)
        ->assertSee('<link rel="apple-touch-icon"', escape: false)
        ->assertSee('navigator.serviceWorker.register', escape: false);
})->with([
    'home page' => '/',
    'login page of the app panel' => '/app/login',
    'knowledge base' => '/knowledge-base',
]);

it('offers to install the app in the app panel, once logged in', function () {
    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->followingRedirects()
        ->get('/app')
        ->assertOk()
        ->assertSee("window.addEventListener('beforeinstallprompt'", escape: false)
        ->assertSee('app-pwa-install', escape: false)
        ->assertSee(__('pwa.install.title', ['app' => config('app.name')]))
        ->assertSee(__('pwa.install.action'))
        ->assertSee(__('pwa.install.dismiss'));
});

it('offers to install the app in a full-screen dialog that recommends it', function () {
    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->followingRedirects()
        ->get('/app')
        ->assertOk()
        ->assertSee('app-pwa-install fixed inset-0', escape: false)
        ->assertSee('aria-modal="true"', escape: false)
        ->assertSee('x-trap.inert.noscroll="isOpen"', escape: false)
        ->assertSee(__('pwa.install.recommended'))
        ->assertSee(__('pwa.install.description', ['app' => config('app.name')]))
        ->assertSee(__('pwa.install.benefits.home_screen.title'))
        ->assertSee(__('pwa.install.benefits.own_window.title'))
        ->assertSee(__('pwa.install.benefits.ios_notifications.title'))
        ->assertSee('x-show="isIos"', escape: false)
        ->assertSee(__('pwa.install.later'))
        ->assertSee(__('pwa.install.dismiss'));
});

it('opens the offer to install the app by itself only on the first page after logging in', function () {
    Filament::setCurrentPanel('app');
    $user = User::factory()->withPassword('a-password')->create(['can_login' => true, 'email_verified_at' => now()]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'a-password'])
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->followingRedirects()->get('/app')->assertOk()->assertSee('offerAfterLogin: true', escape: false);

    // Still rendered, to be opened from the user menu and the profile
    $this->followingRedirects()->get('/app')->assertOk()
        ->assertSee('app-pwa-install', escape: false)
        ->assertSee('offerAfterLogin: false', escape: false);
});

it('does not open the offer to install the app by itself for users already logged in', function () {
    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->followingRedirects()
        ->get('/app')
        ->assertOk()
        ->assertSee('offerAfterLogin: false', escape: false)
        ->assertSee('this.requested = this.offerAfterLogin && ! this.wasDismissedRecently()', escape: false);
});

it('lets users install the app later from the user menu and the profile', function () {
    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->followingRedirects()
        ->get('/app')
        ->assertOk()
        ->assertSee("window.addEventListener('open-pwa-install'", escape: false)
        ->assertSee("Alpine.store('pwaInstall', { available: false, installed: false })", escape: false)
        ->assertSee('x-show="$store.pwaInstall.available"', escape: false)
        ->assertSee(__('pwa.install.menu_label'))
        ->assertSee(__('pwa.install.later'));

    $this->actingAs($user)
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee('app-pwa-install-section', escape: false)
        ->assertSee(__('pwa.install.profile.title'))
        ->assertSee(__('pwa.install.profile.installed'))
        ->assertSee("\$dispatch('open-pwa-install')", escape: false);
});

it('does not offer to install the app on the other pages', function (string $uri) {
    $this->followingRedirects()
        ->get($uri)
        ->assertOk()
        ->assertDontSee('beforeinstallprompt', escape: false);
})->with([
    'home page' => '/',
    'login page of the app panel' => '/app/login',
    'knowledge base' => '/knowledge-base',
]);
