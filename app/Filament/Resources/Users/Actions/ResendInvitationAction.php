<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Send again the invitation to choose their password to a user who has not chosen it yet, e.g. when the previous one
 * expired or got lost. The new link replaces the previous one.
 *
 * As with "Forgot password?", a new invitation can be sent only once the throttle of the `invitations` password broker
 * has passed since the previous one.
 */
class ResendInvitationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resendInvitation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-resources.user.action_resend_invitation'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalHeading(__('filament-resources.user.resend_invitation_modal_heading'))
            ->modalDescription(fn (User $record): string => __('filament-resources.user.resend_invitation_modal_description', ['email' => $record->email]))
            ->modalSubmitActionLabel(__('filament-resources.user.action_resend_invitation'))
            ->authorize('update')
            ->visible(fn (User $record): bool => $record->isAwaitingInvitation())
            ->action(function (User $record): void {
                $broker = Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER);

                if ($broker instanceof PasswordBroker && $broker->getRepository()->recentlyCreatedToken($record)) {
                    Notification::make()
                        ->warning()
                        ->title(__('filament-resources.user.resend_invitation_throttled', [
                            'seconds' => config('auth.passwords.'.ResetPassword::INVITATION_PASSWORD_BROKER.'.throttle'),
                        ]))
                        ->send();

                    return;
                }

                UserInvitationNotification::sendTo($record);

                Notification::make()
                    ->success()
                    ->title(__('filament-resources.user.invitation_sent', ['email' => $record->email]))
                    ->send();
            });
    }
}
