<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->artisanUrl = 'http://artisan.'.preg_replace("/(^https?:\/\/|:.*$|\/\s*$)/", '', config('app.url'));

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
})->with(['/api/queue/process', '/api/schedule/process']);

test('cron endpoints reject missing or wrong tokens', function (string $path, array $headers) {
    config(['app.artisan_cron_token' => 'cron-secret']);

    $this->postJson($this->artisanUrl.$path, [], $headers)
        ->assertUnauthorized();
})->with(['/api/queue/process', '/api/schedule/process'])->with([
    'missing token' => [[]],
    'wrong token' => [['X-Cron-Token' => 'wrong']],
]);

test('the scheduler endpoint runs with a valid cron token', function () {
    config(['app.artisan_cron_token' => 'cron-secret']);

    $this->postJson($this->artisanUrl.'/api/schedule/process', [], ['X-Cron-Token' => 'cron-secret'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('artisan command endpoints reject requests when the token file is missing', function () {
    $this->getJson($this->artisanUrl.'/api/list', ['X-Artisan-Token' => 'anything'])
        ->assertUnauthorized();
});

test('artisan command endpoints reject requests when the token file is blank', function () {
    File::put($this->tokenFile, "\n");

    $this->getJson($this->artisanUrl.'/api/list', ['X-Artisan-Token' => ''])
        ->assertUnauthorized();

    $this->getJson($this->artisanUrl.'/api/list')
        ->assertUnauthorized();
});

test('artisan command endpoints reject a wrong token', function () {
    File::put($this->tokenFile, 'artisan-secret');

    $this->getJson($this->artisanUrl.'/api/list', ['X-Artisan-Token' => 'wrong'])
        ->assertUnauthorized();
});

test('artisan command endpoints accept the token stored in the token file', function () {
    File::put($this->tokenFile, "artisan-secret\n");

    $this->getJson($this->artisanUrl.'/api/list', ['X-Artisan-Token' => 'artisan-secret'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('artisan command parameters must be a string', function () {
    File::put($this->tokenFile, 'artisan-secret');

    $this->postJson($this->artisanUrl.'/api/execute/about', ['params' => ['--json']], ['X-Artisan-Token' => 'artisan-secret'])
        ->assertUnprocessable()
        ->assertJson(['success' => false]);
});
