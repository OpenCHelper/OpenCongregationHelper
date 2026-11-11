<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->withoutVite();
});

test('welcome page describes the project scope', function () {
    Config::set('app.name', 'Registro Territori');

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Registro Territori</title>', escape: false)
        ->assertSeeText(__('welcome.hero.title'))
        ->assertSeeText(__('welcome.hero.description', ['app' => 'Registro Territori']));
});

test('welcome page lists the current features', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSeeText(__('welcome.features.heading'));

    foreach (['territories', 'assignments', 'campaigns', 'overview', 'sharing', 'access'] as $feature) {
        $response
            ->assertSeeText(__("welcome.features.{$feature}.title"))
            ->assertSeeText(__("welcome.features.{$feature}.description"));
    }
});

test('welcome page links to the github repository', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="https://github.com/LukeSavefrogs/registro-territori"', escape: false)
        ->assertSeeText(__('welcome.open_source.action'));
});

test('welcome page shows the application version', function () {
    Config::set('app.version', 'v9.8.7-test');

    $this->get('/')
        ->assertOk()
        ->assertSeeText('v9.8.7-test');
});

test('welcome page links to the user guide', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('filament.knowledge-base.home').'"', escape: false);
});

test('welcome page invites guests to log in', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('filament.app.auth.login').'"', escape: false)
        ->assertSeeText(__('welcome.hero.primary_action_guest'))
        ->assertDontSeeText(__('welcome.hero.primary_action_auth'));
});

test('welcome page links authenticated users to the application', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('filament.app.pages.dashboard').'"', escape: false)
        ->assertSeeText(__('welcome.hero.primary_action_auth'))
        ->assertDontSeeText(__('welcome.nav.login'));
});

test('welcome page is translated in every supported language', function (string $locale) {
    app()->setLocale($locale);

    $this->get('/')
        ->assertOk()
        ->assertSeeText(__('welcome.hero.title', locale: $locale))
        ->assertDontSeeText('welcome.hero.title');
})->with(['it', 'en', 'es']);
