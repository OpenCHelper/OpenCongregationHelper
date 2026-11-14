<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * Login page of the app panel.
 *
 * On demo installations only (PR previews, TEST with dummy data, local development), the form can be pre-filled with
 * the credentials of the root user, so that whoever opens the demo can test it right away: see
 * `DEMO_PREFILL_LOGIN_CREDENTIALS`.
 */
class Login extends BaseLogin
{
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
