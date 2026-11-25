<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    usePassportTestKeys();

    config(['app.initial_root_user' => [
        'name' => 'Mario Rossi',
        'email' => 'root@example.com',
        'password' => 'original-password',
    ]]);

    $this->artisan('och:install', ['--no-interaction' => true])->assertSuccessful();

    // A root user locked out in production: no login, no role, two-factor authentication on, another password
    $this->app->detectEnvironment(fn () => 'production');
    User::rootUser()->forceFill([
        'password' => 'lost-password',
        'can_login' => false,
        'email_verified_at' => null,
        'app_authentication_secret' => 'secret',
        'app_authentication_recovery_codes' => ['code'],
    ])->save();
    User::rootUser()->removeRole('super_admin');
    $this->app->detectEnvironment(fn () => 'testing');

    config(['app.initial_root_user.password' => 'recovered-password']);
});

test('the root user gets its access back', function () {
    Log::spy();

    $this->artisan('och:recover-root', ['--force' => true, '--no-interaction' => true])->assertSuccessful();

    $root = User::rootUser();

    expect(Hash::check('recovered-password', $root->password))->toBeTrue()
        ->and($root->can_login)->toBeTrue()
        ->and($root->email_verified_at)->not->toBeNull()
        ->and($root->hasTwoFactorAuthentication())->toBeFalse()
        ->and($root->hasRole('super_admin'))->toBeTrue();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'och:recover-root'));
});

test('it needs --force when it cannot ask for a confirmation', function () {
    $this->artisan('och:recover-root', ['--no-interaction' => true])
        ->expectsOutputToContain('Pass --force')
        ->assertFailed();

    expect(User::rootUser()->can_login)->toBeFalse();
});

test('nothing changes when the confirmation is declined', function () {
    $this->artisan('och:recover-root')
        ->expectsConfirmation('Reset the password, the login and the two-factor authentication of root@example.com, and make it a super admin?', 'no')
        ->assertSuccessful();

    expect(User::rootUser()->can_login)->toBeFalse();
});

test('the password is asked for when it is not configured', function () {
    config(['app.initial_root_user.password' => null]);

    $this->artisan('och:recover-root', ['--force' => true])
        ->expectsQuestion('New password of the root user', 'typed-password')
        ->assertSuccessful();

    expect(Hash::check('typed-password', User::rootUser()->password))->toBeTrue();
});

test('the typed password follows the password policy', function () {
    config(['app.initial_root_user.password' => null]);

    $this->artisan('och:recover-root', ['--force' => true])
        ->expectsQuestion('New password of the root user', 'short')
        ->expectsOutputToContain(__('validation.min.string', ['attribute' => 'password', 'min' => 8]))
        ->assertFailed();

    expect(Hash::check('short', User::rootUser()->password))->toBeFalse();
});

test('a configured password that breaks the password policy is refused', function () {
    $this->app->detectEnvironment(fn () => 'production');
    fakeLeakedPassword('leaked-password-123');
    config(['app.initial_root_user.password' => 'leaked-password-123']);

    $this->artisan('och:recover-root', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain(__('validation.password.uncompromised', ['attribute' => 'password']))
        ->assertFailed();

    expect(Hash::check('leaked-password-123', User::rootUser()->password))->toBeFalse()
        ->and(User::rootUser()->can_login)->toBeFalse();
});

test('another password is asked for when the configured one breaks the password policy', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.initial_root_user.password' => 'elevenchars']);
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    $this->artisan('och:recover-root', ['--force' => true])
        ->expectsOutputToContain(__('validation.min.string', ['attribute' => 'password', 'min' => 12]))
        ->expectsQuestion('New password of the root user', 'a-long-and-unique-passphrase')
        ->assertSuccessful();

    expect(Hash::check('a-long-and-unique-passphrase', User::rootUser()->password))->toBeTrue();
});

test('it fails without a password when it cannot ask for one', function () {
    config(['app.initial_root_user.password' => null]);

    $this->artisan('och:recover-root', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('INITIAL_ROOT_PASSWORD')
        ->assertFailed();

    expect(User::rootUser()->can_login)->toBeFalse();
});
