<?php

namespace App\Filament\Pages\Auth;

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
            // The success notification is the translation of the broker status: for this request only, it confirms
            // that the password has been chosen instead of reset. The group is loaded first, so that its other lines
            // (e.g. an invalid token) are still translated
            $translator = app('translator');
            $translator->load('*', 'passwords', app()->getLocale());
            $translator->addLines([Password::PASSWORD_RESET => __('filament-pages.invitation.notification')], app()->getLocale());
        }

        return parent::resetPassword();
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
