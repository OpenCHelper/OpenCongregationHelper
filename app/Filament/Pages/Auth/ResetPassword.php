<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Password reset page that also accepts the invitations sent to new users (see `UserInvitationNotification`).
 *
 * The invitation links are signed URLs with the `invitation` parameter: their tokens are checked with the `invitations`
 * password broker, which keeps them in their own table and lets them last longer than the normal password resets. The
 * page then talks about choosing a password, as the invitation email does, instead of resetting it.
 */
class ResetPassword extends BaseResetPassword
{
    public const INVITATION_PASSWORD_BROKER = 'invitations';

    #[Locked]
    public bool $isInvitation = false;

    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null): void
    {
        $this->isInvitation = request()->boolean('invitation');

        $this->useInvitationPasswordBroker();

        parent::mount($email, $token);
    }

    /**
     * Livewire hook called on every request after the first one, which rebuilds the panel.
     */
    public function hydrate(): void
    {
        $this->useInvitationPasswordBroker();
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        if ($this->isInvitation) {
            // The notifications are the translations of the broker statuses: for this request only, they talk about the
            // invitation instead of a password reset (the password has been chosen, or the invitation is no longer
            // valid). The group is loaded first, so that its other lines (e.g. the throttling) are still translated
            $translator = app('translator');
            $translator->load('*', 'passwords', app()->getLocale());
            $translator->addLines([
                Password::PASSWORD_RESET => __('filament-pages.invitation.notification'),
                Password::INVALID_TOKEN => __('filament-pages.invitation.invalid'),
                Password::INVALID_USER => __('filament-pages.invitation.invalid'),
            ], app()->getLocale());
        }

        $response = parent::resetPassword();

        // The invitation was sent to the email of the user, so choosing the password from it confirms the address too:
        // no further confirmation email is needed when the Security settings require one
        if ($this->isInvitation && $response !== null) {
            $user = User::query()->where('email', $this->email)->first();

            if ($user !== null && ! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        return $response;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->isInvitation ? __('filament-pages.invitation.title') : parent::getTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->isInvitation ? __('filament-pages.invitation.heading') : parent::getHeading();
    }

    public function getResetPasswordFormAction(): Action
    {
        $action = parent::getResetPasswordFormAction();

        return $this->isInvitation ? $action->label(__('filament-pages.invitation.submit')) : $action;
    }

    protected function useInvitationPasswordBroker(): void
    {
        if ($this->isInvitation) {
            Filament::getCurrentOrDefaultPanel()->authPasswordBroker(self::INVITATION_PASSWORD_BROKER);
        }
    }
}
