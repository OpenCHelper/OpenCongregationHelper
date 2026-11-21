<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Invites a user created by an administrator to choose their own password.
 *
 * The link is a password reset link of the app panel, so it expires like the ones sent from "Forgot password".
 */
class SetPasswordNotification extends Notification
{
    public function __construct(
        public string $token,
    ) {}

    /**
     * Send the invitation to a user, with a new password reset token.
     */
    public static function sendTo(CanResetPassword $user): void
    {
        $broker = Password::broker(Filament::getPanel('app')->getAuthPasswordBroker());

        $user->notify(new static($broker->createToken($user)));
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $passwordBroker = Filament::getPanel('app')->getAuthPasswordBroker() ?? config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject(__('filament-resources.mailables.set_password_subject', ['app' => config('app.name')]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.set_password_body', ['app' => config('app.name')]))
            ->action(
                __('filament-resources.mailables.set_password_action'),
                Filament::getPanel('app')->getResetPasswordUrl($this->token, $notifiable),
            )
            ->line(__('filament-resources.mailables.set_password_expiration', [
                'count' => config("auth.passwords.{$passwordBroker}.expire"),
            ]));
    }
}
