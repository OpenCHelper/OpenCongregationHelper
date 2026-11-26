<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
    Filament throttles the login by IP address, but the IP address can be chosen by the client when every peer is
    trusted as a proxy (`TRUSTED_PROXIES=*`): the failed attempts are also counted per email.
*/

beforeEach(function () {
    Filament::setCurrentPanel('app');

    $this->user = User::factory()->create([
        'email' => 'publisher@example.com',
        'password' => 'correct-horse-battery-staple',
        'email_verified_at' => now(),
        'can_login' => true,
    ]);
});

/**
 * Tries to log in as a client rotating `X-Forwarded-For` would: every attempt comes from a "new" IP address, so the
 * throttling by IP address of Filament never kicks in (its counter is reset, since Livewire tests have no proxy).
 */
function attemptLoginFromNewAddress(string $email, string $password): Testable
{
    RateLimiter::clear('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1'));

    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

it('blocks an email after too many failed attempts, whatever the IP address', function () {
    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL) as $attempt) {
        attemptLoginFromNewAddress('publisher@example.com', 'wrong-password-'.$attempt)
            ->assertHasFormErrors(['email']);
    }

    attemptLoginFromNewAddress('publisher@example.com', 'correct-horse-battery-staple')
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-panels::auth/pages/login.notifications.throttled.title', [
            'seconds' => Login::FAILED_ATTEMPTS_DECAY_SECONDS,
            'minutes' => Login::FAILED_ATTEMPTS_DECAY_SECONDS / 60,
        ]));

    $this->assertGuest();
});

it('counts the email without regard to case and surrounding spaces', function () {
    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL) as $attempt) {
        attemptLoginFromNewAddress(' Publisher@Example.com ', 'wrong-password-'.$attempt);
    }

    attemptLoginFromNewAddress('publisher@example.com', 'correct-horse-battery-staple');

    $this->assertGuest();
});

it('counts every variant of an email that signs in to the same account together', function () {
    // MySQL compares the emails ignoring accents too (`utf8mb4_unicode_ci`): each variant finds the same account
    $variants = ['publishér@example.com', 'publìsher@example.com', 'publisher@exámple.com', 'pùblisher@example.com', 'publisher@example.com'];

    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL) as $attempt) {
        attemptLoginFromNewAddress($variants[$attempt % count($variants)], 'wrong-password-'.$attempt);
    }

    attemptLoginFromNewAddress('publisher@example.com', 'correct-horse-battery-staple');

    $this->assertGuest();
})->skip(fn (): bool => DB::getDriverName() !== 'mysql', 'Only MySQL compares the emails ignoring accents.');

it('counts the failed attempts of an email without an account by its text', function () {
    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL) as $attempt) {
        attemptLoginFromNewAddress('Nobody@Example.com', 'wrong-password-'.$attempt);
    }

    attemptLoginFromNewAddress('nobody@example.com', 'any-password')
        ->assertNotified(__('filament-panels::auth/pages/login.notifications.throttled.title', [
            'seconds' => Login::FAILED_ATTEMPTS_DECAY_SECONDS,
            'minutes' => Login::FAILED_ATTEMPTS_DECAY_SECONDS / 60,
        ]));
});

it('does not block the other emails', function () {
    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL) as $attempt) {
        attemptLoginFromNewAddress('someone-else@example.com', 'wrong-password-'.$attempt);
    }

    attemptLoginFromNewAddress('publisher@example.com', 'correct-horse-battery-staple')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->user);
});

it('forgets the failed attempts of an email after a successful login', function () {
    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL - 1) as $attempt) {
        attemptLoginFromNewAddress('publisher@example.com', 'wrong-password-'.$attempt);
    }

    attemptLoginFromNewAddress('publisher@example.com', 'correct-horse-battery-staple');
    $this->assertAuthenticatedAs($this->user);
    auth()->logout();

    // A new series of failed attempts starts from zero
    foreach (range(1, Login::MAX_FAILED_ATTEMPTS_PER_EMAIL - 1) as $attempt) {
        attemptLoginFromNewAddress('publisher@example.com', 'wrong-password-'.$attempt);
    }

    attemptLoginFromNewAddress('publisher@example.com', 'correct-horse-battery-staple');
    $this->assertAuthenticatedAs($this->user);
});
