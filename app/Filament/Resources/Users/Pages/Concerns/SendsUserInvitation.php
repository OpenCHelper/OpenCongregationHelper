<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Enums\PasswordSetup;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;

/**
 * Sends the invitation to choose their password to a user given access from the user form, when the administrator
 * chose {@see PasswordSetup::Invitation} (see `UserResource::isChoosingPasswordSetup()`).
 *
 * @property User $record
 */
trait SendsUserInvitation
{
    protected bool $shouldSendInvitation = false;

    /**
     * Remove the password setup choice from the data saved on the user, and remember whether to send the invitation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullPasswordSetup(array $data): array
    {
        $passwordSetup = Arr::pull($data, 'password_setup');

        $this->shouldSendInvitation = ($passwordSetup instanceof PasswordSetup ? $passwordSetup : PasswordSetup::tryFrom((string) $passwordSetup))
            === PasswordSetup::Invitation;

        if ($this->shouldSendInvitation) {
            unset($data['password']);
        }

        return $data;
    }

    protected function sendInvitationIfChosen(): void
    {
        if (! $this->shouldSendInvitation) {
            return;
        }

        UserInvitationNotification::sendTo($this->record);

        Notification::make()
            ->success()
            ->title(__('filament-resources.user.invitation_sent', ['email' => $this->record->email]))
            ->send();
    }
}
