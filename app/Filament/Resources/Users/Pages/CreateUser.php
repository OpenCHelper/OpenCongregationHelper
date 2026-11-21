<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

/**
 * @property User $record
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Whether the new user must receive an email to set their own password.
     */
    protected bool $shouldSendSetPasswordLink = false;

    /**
     * When an administrator leaves the password empty for a user that can log in, the user gets a random password
     * that nobody knows, and an email to set their own.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->shouldSendSetPasswordLink = ($data['can_login'] ?? false) && blank($data['password'] ?? null);

        if ($this->shouldSendSetPasswordLink) {
            $data['password'] = Str::password(64);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldSendSetPasswordLink) {
            return;
        }

        SetPasswordNotification::sendTo($this->record);

        Notification::make()
            ->success()
            ->title(__('filament-resources.user.set_password_link_sent', ['email' => $this->record->email]))
            ->send();
    }
}
