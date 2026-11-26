<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login page of the app panel.
 *
 * On demo installations only (PR previews, TEST with dummy data, local development), the form can be pre-filled with
 * the credentials of the root user, so that whoever opens the demo can test it right away: see
 * `DEMO_PREFILL_LOGIN_CREDENTIALS`.
 *
 * Besides the throttling by IP address of Filament, the failed attempts are also counted per account: the IP address
 * can be chosen by the client when every peer is trusted as a proxy (see `TRUSTED_PROXIES`), so it alone would not
 * stop guessing the password (or the two-factor code) of an account.
 */
class Login extends BaseLogin
{
    /**
     * Failed attempts allowed for the same account (or email) before the login is blocked for it.
     */
    public const MAX_FAILED_ATTEMPTS_PER_EMAIL = 10;

    /**
     * Seconds after which the failed attempts for an account (or email) are forgotten.
     */
    public const FAILED_ATTEMPTS_DECAY_SECONDS = 15 * 60;

    public function authenticate(): ?LoginResponse
    {
        $rateLimitKey = $this->getEmailRateLimitKey();

        if (($rateLimitKey !== null) && RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_FAILED_ATTEMPTS_PER_EMAIL)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($rateLimitKey),
            ))?->send();

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            // Wrong credentials or two-factor code
            if ($rateLimitKey !== null) {
                RateLimiter::hit($rateLimitKey, self::FAILED_ATTEMPTS_DECAY_SECONDS);
            }

            throw $exception;
        }

        if (($response !== null) && ($rateLimitKey !== null)) {
            RateLimiter::clear($rateLimitKey);
        }

        return $response;
    }

    /**
     * Key of the failed attempts for the email typed in the form, or `null` when there is none.
     *
     * The attempts are counted per account, found like the login finds it: the database compares the emails ignoring
     * case and accents (e.g. `utf8mb4_unicode_ci` on MySQL), so every variant of an email that signs in to an account
     * shares its counter. Emails of no account are counted by their lower-cased text.
     */
    protected function getEmailRateLimitKey(): ?string
    {
        $email = $this->data['email'] ?? null;

        if (! is_string($email) || blank($email)) {
            return null;
        }

        $user = Filament::auth()->getProvider()->retrieveByCredentials(['email' => trim($email)]);

        return $user !== null
            ? 'login-user:'.sha1((string) $user->getAuthIdentifier())
            : 'login-email:'.sha1(Str::lower(trim($email)));
    }

    #[Unexposed]
    public function mount(): void
    {
        parent::mount();

        if ($this->shouldPrefillCredentials()) {
            $this->form->fill([
                'email' => config('app.initial_root_user.email'),
                'password' => config('app.initial_root_user.password'),
                'remember' => true,
            ]);
        }
    }

    /**
     * The email field is not focused when the credentials are pre-filled: there is nothing to type, the user most
     * likely just wants to click on the login button.
     */
    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();

        if ($component instanceof TextInput) {
            $component->autofocus(! $this->shouldPrefillCredentials());
        }

        return $component;
    }

    /**
     * Whether to pre-fill the form with the credentials of the root user.
     *
     * Only when `DEMO_PREFILL_LOGIN_CREDENTIALS` is enabled, which MUST be done on demo installations only, and never
     * in production, whatever its value: anyone opening the login page could log in as the root user.
     */
    protected function shouldPrefillCredentials(): bool
    {
        return config('app.demo_prefill_login_credentials')
            && ! app()->environment('production')
            && filled(config('app.initial_root_user.email'))
            && filled(config('app.initial_root_user.password'));
    }
}
