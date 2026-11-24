<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    usePassportTestKeys();

    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'https://och-instance.example.com',
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

test('the cron endpoints are optional', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    Artisan::call('och:doctor');
    $warningsWithToken = substr_count(Artisan::output(), 'WARNING');

    config(['app.artisan_cron_token' => null]);
    $exitCode = Artisan::call('och:doctor');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('disabled (only needed without a shell)')
        ->and(substr_count($output, 'WARNING'))->toBe($warningsWithToken);
});

test('a root user that cannot log in is reported', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();
    User::rootUser()->forceFill(['can_login' => false])->saveQuietly();

    $this->artisan('och:doctor')
        ->expectsOutputToContain('och:recover-root')
        ->assertSuccessful();
});
