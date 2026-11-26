<?php

use App\Models\User;
use App\Support\HttpsCertificate;
use App\Support\InstallationChecks;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    usePassportTestKeys();

    // No connection to the host of APP_URL
    app()->instance(HttpsCertificate::class, new class extends HttpsCertificate
    {
        public function inspect(string $host, int $port = 443): array
        {
            return ['valid' => true, 'expires_at' => now()->addYear()->toImmutable(), 'error' => null];
        }
    });

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
        ->expectsOutputToContain('Root user')
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

test('the push notifications and the security of the root user are reported', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();
    config(['webpush.vapid.public_key' => null, 'app.url' => 'http://och-instance.example.com']);

    $this->artisan('och:doctor')
        ->expectsOutputToContain('the push notifications are off')
        ->expectsOutputToContain('APP_URL does not start')
        ->expectsOutputToContain('Two-factor authentication of the root user')
        ->assertSuccessful();
});

test('the report is in English whatever the language of the app', function () {
    app()->setLocale('it');

    $this->artisan('och:doctor')
        ->expectsOutputToContain('Root user')
        ->assertFailed();
});

test('an HTTPS certificate that is not valid is an error', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    app()->instance(HttpsCertificate::class, new class extends HttpsCertificate
    {
        public function inspect(string $host, int $port = 443): array
        {
            return ['valid' => false, 'expires_at' => null, 'error' => 'certificate has expired'];
        }
    });

    $this->artisan('och:doctor')
        ->expectsOutputToContain('the certificate is not valid')
        ->assertFailed();
});

test('an HTTPS certificate that cannot be checked is a warning', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    app()->instance(HttpsCertificate::class, new class extends HttpsCertificate
    {
        public function inspect(string $host, int $port = 443): array
        {
            return ['valid' => null, 'expires_at' => null, 'error' => 'the certificate could not be read'];
        }
    });

    $this->artisan('och:doctor')
        ->expectsOutputToContain('the certificate could not be checked')
        ->assertSuccessful();
});

test('the PHP extensions required by the packages are checked', function () {
    expect(InstallationChecks::requiredExtensions())
        // Required by packages (e.g. lcobucci/jwt, openspout/openspout), not only by the application
        ->toContain('sodium', 'zip', 'intl', 'pdo')
        ->not->toContain('xdebug');
});

test('a setup token left after the installation is reported', function () {
    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    config(['app.setup.token' => 'the-setup-token']);

    $this->artisan('och:doctor')
        ->expectsOutputToContain('still set although the app is installed')
        ->assertSuccessful();

    config(['app.setup.token' => null]);

    $this->artisan('och:doctor')
        ->doesntExpectOutputToContain('still set although the app is installed')
        ->assertSuccessful();
});
