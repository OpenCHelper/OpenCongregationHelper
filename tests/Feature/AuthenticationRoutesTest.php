<?php

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as PanelResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;

beforeEach(function () {
    // The user with ID 1 is the root user, whose email and password cannot be changed
    User::factory()->create(['id' => 1]);

    Filament::setCurrentPanel('app');

    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

test('the password reset of Fortify is not reachable outside the panel', function (string $method, string $uri, array $data) {
    Notification::fake();

    User::factory()->create(['email' => 'known@example.com']);

    $this->call($method, $uri, $data)->assertNotFound();

    Notification::assertNothingSent();
})->with([
    'request page' => ['GET', '/forgot-password', []],
    'link for a known email' => ['POST', '/forgot-password', ['email' => 'known@example.com']],
    'link for an unknown email' => ['POST', '/forgot-password', ['email' => 'unknown@example.com']],
    'reset page' => ['GET', '/reset-password/abc', []],
    'reset' => ['POST', '/reset-password', [
        'token' => 'abc',
        'email' => 'known@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]],
]);

test('the account routes of Fortify are not reachable outside the panel', function (string $method, string $uri, array $data) {
    $this->actingAs(User::factory()->verified()->create())
        ->call($method, $uri, $data)
        ->assertNotFound();
})->with([
    'email verification notice' => ['GET', '/email/verify', []],
    'email verification' => ['GET', '/email/verify/2/hash', []],
    'email verification notification' => ['POST', '/email/verification-notification', []],
    'password confirmation page' => ['GET', '/user/confirm-password', []],
    'password confirmation' => ['POST', '/user/confirm-password', ['password' => 'password']],
    'password confirmation status' => ['GET', '/user/confirmed-password-status', []],
    'password update' => ['PUT', '/user/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]],
    'profile update' => ['PUT', '/user/profile-information', ['first_name' => 'Changed']],
    'logout' => ['POST', '/logout', []],
]);

test('the registration page does not exist while the registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('the registration page leads to the panel when the registration is enabled', function () {
    Config::set('fortify.features', [...config('fortify.features'), Features::registration()]);

    // The routes are registered when the application boots, so they must be loaded again
    Route::middleware('web')->group(base_path('routes/web.php'));
    Route::getRoutes()->refreshNameLookups();

    expect(route('register', absolute: false))->toBe('/register');

    $this->get('/register')->assertRedirect('/app/register');
});

test('the password reset of the panel sends a link to its reset page', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->get('/app/password-reset/request')->assertOk();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($user, PanelResetPassword::class, function (PanelResetPassword $notification): bool {
        expect(parse_url($notification->url, PHP_URL_PATH))->toBe('/app/password-reset/reset');

        $this->get($notification->url)->assertOk();

        return true;
    });
});

test('the default password reset notification links to the reset page of the panel', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->sendPasswordResetNotification(Password::broker()->createToken($user));

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $resetUrl = $notification->toMail($user)->actionUrl;

        expect(parse_url($resetUrl, PHP_URL_PATH))->toBe('/app/password-reset/reset');

        $this->get($resetUrl)->assertOk();

        return true;
    });
});

test('the verification link sent from the profile verifies the email in the panel', function () {
    Notification::fake();

    $this->actingAs($user = User::factory()->create());

    Livewire::test(UpdateProfileInformationForm::class)
        ->call('sendEmailVerification')
        ->assertSet('verificationLinkSent', true);

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $verificationUrl = $notification->toMail($user)->actionUrl;

        expect(parse_url($verificationUrl, PHP_URL_PATH))->toStartWith('/app/email-verification/verify/');

        $this->get($verificationUrl)->assertRedirect();

        return true;
    });

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('changing the email from the profile sends a verification link of the panel', function () {
    Notification::fake();

    $this->actingAs($user = User::factory()->verified()->create());

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('state', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'changed@example.com',
        ])
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->email->toBe('changed@example.com')
        ->hasVerifiedEmail()->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $verificationUrl = $notification->toMail($user->fresh())->actionUrl;

        expect(parse_url($verificationUrl, PHP_URL_PATH))->toStartWith('/app/email-verification/verify/');

        return true;
    });
});
