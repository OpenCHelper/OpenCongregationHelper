<?php

namespace App\Notifications;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TerritoryReturnedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TerritoryAssignment $assignment,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('filament-resources.mailables.territory_returned_subject', ['territory' => $this->assignment->territory->name]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.territory_returned_body', ['territory' => $this->assignment->territory->name]))
            ->action(__('filament-resources.mailables.view_details'), TerritoryAssignmentResource::getUrl('view', [
                'record' => $this->assignment->id,
            ]))
            ->line(__('filament-resources.mailables.thank_you'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('filament-resources.database_notifications.territory_returned_title', ['territory' => $this->assignment->territory->name]))
            ->body(__('filament-resources.database_notifications.territory_returned_body'))
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->getDatabaseMessage();
    }
}
