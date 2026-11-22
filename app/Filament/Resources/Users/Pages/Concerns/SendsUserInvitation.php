<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Filament\Notifications\Notification;

/**
 * Sends the invitation to choose their password to a user given access from the user form (see
 * {@see UserResource::willSendInvitation()}). Administrators never set passwords: users choose their own.
 *
 * @property User $record
 */
trait SendsUserInvitation
{
    protected bool $shouldSendInvitation = false;

    /**
     * Remember whether to send the invitation, and drop any password from the data saved on the user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareInvitation(array $data, ?User $record): array
    {
        unset($data['password']);

        $this->shouldSendInvitation = UserResource::willSendInvitation((bool) ($data['can_login'] ?? false), $record);

        return $data;
    }

    protected function sendInvitationIfNeeded(): void
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
