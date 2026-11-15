<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->artisanUrl = rtrim(config('app.url'), '/').'/api/artisan';

    // Preserve any token file already present on the developer machine
    $this->tokenFile = storage_path('framework/.artisan-token');
    $this->originalToken = File::exists($this->tokenFile) ? File::get($this->tokenFile) : null;
    File::delete($this->tokenFile);
});

afterEach(function () {
    File::delete($this->tokenFile);

    if ($this->originalToken !== null) {
        File::put($this->tokenFile, $this->originalToken);
    }
});

test('cron endpoints are disabled when no cron token is configured', function (string $path) {
    config(['app.artisan_cron_token' => null]);

    $this->postJson($this->artisanUrl.$path, [], ['X-Cron-Token' => ''])
        ->assertUnauthorized();
})->with(['/queue/process', '/schedule/process']);

test('cron endpoints reject missing or wrong tokens', function (string $path, array $headers) {
    config(['app.artisan_cron_token' => 'cron-secret']);

    $this->postJson($this->artisanUrl.$path, [], $headers)
        ->assertUnauthorized();
})->with(['/queue/process', '/schedule/process'])->with([
    'missing token' => [[]],
    'wrong token' => [['X-Cron-Token' => 'wrong']],
]);

test('the scheduler endpoint runs with a valid cron token', function () {
    config(['app.artisan_cron_token' => 'cron-secret']);

    $this->postJson($this->artisanUrl.'/schedule/process', [], ['X-Cron-Token' => 'cron-secret'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('jobs processed through the cron endpoints build URLs from the application URL', function () {
    config(['app.artisan_cron_token' => 'cron-secret', 'app.url' => 'https://registro.example.test/']);

    $this->postJson($this->artisanUrl.'/schedule/process', [], ['X-Cron-Token' => 'cron-secret'])
        ->assertOk();

    // Not the host of the request (e.g. links in queued notification emails)
    expect(url('/app/territory-assignments/1'))->toBe('https://registro.example.test/app/territory-assignments/1');
});

test('artisan command endpoints reject requests when the token file is missing', function () {
    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token' => 'anything'])
        ->assertUnauthorized();
});

test('artisan command endpoints reject requests when the token file is blank', function () {
    File::put($this->tokenFile, "\n");

    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token' => ''])
        ->assertUnauthorized();

    $this->getJson($this->artisanUrl.'/list')
        ->assertUnauthorized();
});

test('artisan command endpoints reject a wrong token', function () {
    File::put($this->tokenFile, 'artisan-secret');

    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token' => 'wrong'])
        ->assertUnauthorized();
});

test('artisan command endpoints accept the token stored in the token file', function () {
    File::put($this->tokenFile, "artisan-secret\n");

    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token' => 'artisan-secret'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('artisan command endpoints cannot be reached without a token, even in the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    // The header of the token bypass once allowed in the local environment
    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token-Bypass' => 'true'])
        ->assertUnauthorized();

    $this->postJson($this->artisanUrl.'/execute/about', [], ['X-Artisan-Token-Bypass' => 'true'])
        ->assertUnauthorized();

    File::put($this->tokenFile, 'artisan-secret');

    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token-Bypass' => 'true'])
        ->assertUnauthorized();

    $this->getJson($this->artisanUrl.'/list', ['X-Artisan-Token-Bypass' => 'true', 'X-Artisan-Token' => 'wrong'])
        ->assertUnauthorized();
});

test('artisan command parameters must be a string', function () {
    File::put($this->tokenFile, 'artisan-secret');

    $this->postJson($this->artisanUrl.'/execute/about', ['params' => ['--json']], ['X-Artisan-Token' => 'artisan-secret'])
        ->assertUnprocessable()
        ->assertJson(['success' => false]);
});

test('artisan endpoints are no longer served on the old artisan subdomain', function () {
    File::put($this->tokenFile, 'artisan-secret');
    config(['app.artisan_cron_token' => 'cron-secret']);

    $oldArtisanUrl = 'http://artisan.'.parse_url(config('app.url'), PHP_URL_HOST);

    $this->getJson($oldArtisanUrl.'/api/list', ['X-Artisan-Token' => 'artisan-secret'])
        ->assertNotFound();

    $this->postJson($oldArtisanUrl.'/api/queue/process', [], ['X-Cron-Token' => 'cron-secret'])
        ->assertNotFound();
});
