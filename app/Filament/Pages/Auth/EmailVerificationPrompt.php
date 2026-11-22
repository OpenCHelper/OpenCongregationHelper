<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Filament\Facades\Filament;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Page asking the users to confirm their email address, when the Security settings require it.
 *
 * The email is sent when the page opens, once per session: the users created by an administrator have never been sent
 * one, while the page tells them it was sent. "Resend it" still sends it again.
 */
class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    /**
     * Session key telling that the email was already sent in the session.
     */
    public const SENT_SESSION_KEY = 'email_verification_sent';

    #[Unexposed]
    public function mount(): void
    {
        parent::mount();

        $user = Filament::auth()->user();

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail() || session()->has(self::SENT_SESSION_KEY)) {
            return;
        }

        $this->sendEmailVerificationNotification($user);

        session()->put(self::SENT_SESSION_KEY, true);
    }
}
