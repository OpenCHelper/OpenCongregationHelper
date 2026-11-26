<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Checks that the emails of the app reach their recipients, sent from the first-run setup wizard (`SetupWizard`).
 */
class TestEmailNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('setup.checks.test_email.subject', ['app' => config('app.name')]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('setup.checks.test_email.body', ['app' => config('app.name')]));
    }
}
