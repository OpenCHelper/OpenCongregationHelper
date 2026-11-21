<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Password reset page that also accepts the invitations sent to new users (see `UserInvitationNotification`).
 *
 * The invitation links are signed URLs with the `invitation` parameter: their tokens are checked with the `invitations`
 * password broker, which keeps them in their own table and lets them last longer than the normal password resets.
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

    protected function useInvitationPasswordBroker(): void
    {
        if ($this->isInvitation) {
            Filament::getCurrentOrDefaultPanel()->authPasswordBroker(self::INVITATION_PASSWORD_BROKER);
        }
    }
}
