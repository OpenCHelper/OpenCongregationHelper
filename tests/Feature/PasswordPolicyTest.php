<?php

use App\Filament\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use App\Settings\SecuritySettings;
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
use Spatie\Permission\Models\Role;

/**
 * @return array<string, string>
 */
function passwordErrors(string $password): array
{
    return Validator::make(['password' => $password], ['password' => Password::default()])->errors()->get('password');
}

/**
 * Turn the leak check on or off from the Security settings.
 */
function setPasswordLeakCheck(bool $isEnabled): void
{
    $settings = app(SecuritySettings::class);
    $settings->password_leak_check = $isEnabled;
    $settings->save();
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
    useProductionPasswordPolicy();
    fakeLeakedPassword('leaked-password-123');

    expect(passwordErrors('elevenchars'))->toBe([__('validation.min.string', ['attribute' => 'password', 'min' => 12])])
        ->and(passwordErrors('leaked-password-123'))->toBe([__('validation.password.uncompromised', ['attribute' => 'password'])])
        ->and(passwordErrors('a-long-and-unique-passphrase'))->toBeEmpty();
});

test('the leak check can be turned off from the Security settings', function () {
    useProductionPasswordPolicy();
    Http::fake();
    setPasswordLeakCheck(false);

    expect(passwordErrors('leaked-password-123'))->toBeEmpty()
        ->and(passwordErrors('elevenchars'))->not->toBeEmpty();

    Http::assertNothingSent();
});

test('the leak check can be turned on outside of production', function (bool $fromSettings) {
    fakeLeakedPassword('leaked-password-123');

    if ($fromSettings) {
        setPasswordLeakCheck(true);
    } else {
        config(['auth.password_leak_check.enabled' => true]);
    }

    expect(passwordErrors('leaked-password-123'))->toBe([__('validation.password.uncompromised', ['attribute' => 'password'])])
        ->and(passwordErrors('a-long-and-unique-passphrase'))->toBeEmpty()
        ->and(passwordErrors('12345678'))->toBeEmpty();
})->with(['from the Security settings' => true, 'from .env' => false]);

test('the leak check follows .env until changed from the Security settings', function () {
    config(['auth.password_leak_check.enabled' => true]);

    expect(app(SecuritySettings::class)->password_leak_check)->toBeTrue();

    config(['auth.password_leak_check.enabled' => false]);
    app()->forgetInstance(SecuritySettings::class);

    expect(app(SecuritySettings::class)->password_leak_check)->toBeFalse();
});

test('the leak check is shown in the Security settings', function () {
    Filament::setCurrentPanel('app');
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($superAdmin);

    Livewire::test(ManageSecuritySettings::class)
        ->assertFormFieldExists('password_leak_check')
        ->assertSee(__('settings.default', ['value' => __('settings.fields.password_leak_check.disabled')]))
        ->fillForm(['password_leak_check' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(SecuritySettings::class);

    expect(app(SecuritySettings::class)->password_leak_check)->toBeTrue();
});

test('the leak check gives up quickly when the service does not answer', function () {
    $verifier = app(UncompromisedVerifier::class);

    expect($verifier)->toBeInstanceOf(NotPwnedVerifier::class)
        ->and((fn (): int => $this->timeout)->call($verifier))->toBe(config('auth.password_leak_check.timeout'))
        ->and(config('auth.password_leak_check.timeout'))->toBeLessThanOrEqual(5);
});

test('the profile applies the password policy', function () {
    useProductionPasswordPolicy();
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
    useProductionPasswordPolicy();
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
