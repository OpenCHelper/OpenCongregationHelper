<?php

use App\Models\User;

beforeEach(function () {
    usePassportTestKeys();

    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'https://territories.example.com',
        'app.artisan_cron_token' => 'cron-secret',
        'app.initial_root_user' => ['name' => 'Root', 'email' => 'root@example.com', 'password' => 'secret-password'],
    ]);
});

test('a complete installation has no errors', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    $this->artisan('och:doctor')
        ->expectsOutputToContain('Root user (ID 1)')
        ->doesntExpectOutputToContain('ERROR')
        ->assertSuccessful();
});

test('a missing root user and super admin are errors', function () {
    $this->artisan('och:doctor')
        ->expectsOutputToContain('missing: run `php artisan och:install`')
        ->expectsOutputToContain('nobody can manage the application')
        ->assertFailed();
});

test('a missing application key is an error', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();
    config(['app.key' => '']);

    $this->artisan('och:doctor')
        ->expectsOutputToContain('run `php artisan key:generate`')
        ->assertFailed();
});

test('the disabled cron endpoints are only a warning', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();
    config(['app.artisan_cron_token' => null]);

    $this->artisan('och:doctor')
        ->expectsOutputToContain('the queued emails and the scheduled tasks never run')
        ->assertSuccessful();
});

test('a root user that cannot log in is reported', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();
    User::rootUser()->forceFill(['can_login' => false])->saveQuietly();

    $this->artisan('och:doctor')
        ->expectsOutputToContain('och:recover-root')
        ->assertSuccessful();
});
