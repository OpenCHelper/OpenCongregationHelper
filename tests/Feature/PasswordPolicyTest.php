<?php

use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Livewire\Livewire;

/**
 * @return array<string, string>
 */
function passwordErrors(string $password): array
{
    return Validator::make(['password' => $password], ['password' => Password::default()])->errors()->get('password');
}

beforeEach(function () {
    // The user with ID 1 is the root user, whose password cannot be changed
    User::factory()->create(['id' => 1]);
});

test('outside of production the passwords need 8 characters and are not checked against data leaks', function () {
    Http::fake();

    expect(passwordErrors('short'))->not->toBeEmpty()
        ->and(passwordErrors('12345678'))->toBeEmpty();

    Http::assertNothingSent();
});

test('in production the passwords need 12 characters and must not have leaked', function () {
    $this->app->detectEnvironment(fn () => 'production');
    fakeLeakedPassword('leaked-password-123');

    expect(passwordErrors('elevenchars'))->toBe([__('validation.min.string', ['attribute' => 'password', 'min' => 12])])
        ->and(passwordErrors('leaked-password-123'))->toBe([__('validation.password.uncompromised', ['attribute' => 'password'])])
        ->and(passwordErrors('a-long-and-unique-passphrase'))->toBeEmpty();
});

test('the leak check can be turned off', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['auth.password_leak_check.enabled' => false]);
    Http::fake();

    expect(passwordErrors('leaked-password-123'))->toBeEmpty()
        ->and(passwordErrors('elevenchars'))->not->toBeEmpty();

    Http::assertNothingSent();
});

test('the leak check gives up quickly when the service does not answer', function () {
    $verifier = app(UncompromisedVerifier::class);

    expect($verifier)->toBeInstanceOf(NotPwnedVerifier::class)
        ->and((fn (): int => $this->timeout)->call($verifier))->toBe(config('auth.password_leak_check.timeout'))
        ->and(config('auth.password_leak_check.timeout'))->toBeLessThanOrEqual(5);
});

test('the profile applies the password policy', function () {
    $this->app->detectEnvironment(fn () => 'production');
    fakeLeakedPassword('leaked-password-123');
    $this->actingAs($user = User::factory()->create());

    foreach (['too-short', 'leaked-password-123'] as $password) {
        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => 'password',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->call('updatePassword')
            ->assertHasErrors(['password']);
    }

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the invitation and password reset page applies the password policy', function (bool $isInvitation) {
    $this->app->detectEnvironment(fn () => 'production');
    fakeLeakedPassword('leaked-password-123');
    Filament::setCurrentPanel('app');

    $user = User::factory()->create(['can_login' => true, 'password' => null]);
    $token = PasswordBroker::broker($isInvitation ? ResetPassword::INVITATION_PASSWORD_BROKER : null)->createToken($user);

    foreach (['too-short', 'leaked-password-123'] as $password) {
        Livewire::withQueryParams(['invitation' => $isInvitation])
            ->test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
            ->fillForm(['password' => $password, 'passwordConfirmation' => $password])
            ->call('resetPassword')
            ->assertHasFormErrors(['password']);
    }

    expect($user->refresh()->password)->toBeNull();
})->with(['invitation' => true, 'password reset' => false]);

test('the validation messages of the password policy are translated', function (string $locale) {
    app()->setLocale($locale);

    foreach (['validation.min.string', 'validation.password.uncompromised'] as $key) {
        expect(trans()->hasForLocale($key, $locale))->toBeTrue();
    }
})->with(['en', 'it', 'es']);
