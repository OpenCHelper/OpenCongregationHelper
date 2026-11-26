<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login page of the app panel.
 *
 * On demo installations only (PR previews, TEST with dummy data, local development), the form can be pre-filled with
 * the credentials of the root user, so that whoever opens the demo can test it right away: see
 * `DEMO_PREFILL_LOGIN_CREDENTIALS`.
 *
 * Besides the throttling by IP address of Filament, the attempts are also counted per email: the IP address can be
 * chosen by the client when every peer is trusted as a proxy (see `TRUSTED_PROXIES`), so it alone would not stop
 * guessing the password (or the two-factor code) of an account.
 */
class Login extends BaseLogin
{
    /**
     * Attempts allowed with the same email in each period of `ATTEMPTS_DECAY_SECONDS`: a successful login starts over.
     */
    public const MAX_ATTEMPTS_PER_EMAIL = 10;

    /**
     * Length in seconds of the period in which the attempts of an email are counted, from the first one.
     */
    public const ATTEMPTS_DECAY_SECONDS = 15 * 60;

    public function authenticate(): ?LoginResponse
    {
        $rateLimitKey = $this->getEmailRateLimitKey();

        // Counted before the password is checked, so that requests sent in parallel cannot go past the limit
        if (($rateLimitKey !== null) && (RateLimiter::increment($rateLimitKey, self::ATTEMPTS_DECAY_SECONDS) > self::MAX_ATTEMPTS_PER_EMAIL)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($rateLimitKey),
            ))?->send();

            return null;
        }

        $response = parent::authenticate();

        if (($response !== null) && ($rateLimitKey !== null)) {
            RateLimiter::clear($rateLimitKey);
        }

        return $response;
    }

    /**
     * Key of the attempts of the email typed in the form, or `null` when there is none.
     *
     * The database finds an account ignoring case and accents (e.g. `utf8mb4_unicode_ci` on MySQL), so the email is
     * folded the same way: every spelling that signs in to an account shares its counter. The account is not looked up,
     * so that the counter behaves the same whether it exists or not, and does not tell which emails have an account.
     */
    protected function getEmailRateLimitKey(): ?string
    {
        $email = $this->data['email'] ?? null;

        if (! is_string($email) || blank($email)) {
            return null;
        }

        return 'login-email:'.sha1(Str::lower(Str::ascii(trim($email))));
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
