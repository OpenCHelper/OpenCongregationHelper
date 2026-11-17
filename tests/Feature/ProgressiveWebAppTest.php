<?php

use App\Http\Controllers\WebManifestController;

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
