<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Checks that the emails of the app reach their recipients, sent from the setup wizard (`SetupWizard`) before the
 * recipient has an account.
 */
class TestEmailNotification extends Notification
{
    public function __construct(public string $name) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('setup.checks.test_email.subject', ['app' => config('app.name')]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $this->name]))
            ->line(__('setup.checks.test_email.body', ['app' => config('app.name')]));
    }
}
