<?php

use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keys = sys_get_temp_dir().'/passport-keys-test-'.Str::random(8);
    mkdir($this->keys);
    Passport::loadKeysFrom($this->keys);
});

afterEach(function () {
    array_map('unlink', glob($this->keys.'/*') ?: []);
    rmdir($this->keys);

    // The next tests of the same process would look for the keys in the folder just deleted
    Passport::$keyPath = null;
});

test('the keys are created when missing, and kept when they exist', function () {
    $this->artisan('och:ensure-passport-keys', ['--length' => 1024])->assertSuccessful();

    $privateKey = file_get_contents($this->keys.'/oauth-private.key');
    expect($privateKey)->toContain('PRIVATE KEY')
        ->and(file_get_contents($this->keys.'/oauth-public.key'))->toContain('PUBLIC KEY');

    $this->artisan('och:ensure-passport-keys', ['--length' => 1024])->assertSuccessful();

    expect(file_get_contents($this->keys.'/oauth-private.key'))->toBe($privateKey);
});
