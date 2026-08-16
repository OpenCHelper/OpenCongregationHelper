<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Models\User;
use App\Models\TerritoryAssignment;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;

class TerritoryAssignedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TerritoryAssignment $assignment,
    )
    {
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
            ->subject(__('filament-resources.mailables.territory_assigned_subject', ['territory' => $this->assignment->territory->name]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.territory_assigned_body', ['territory' => $this->assignment->territory->name]))
            ->line(__('filament-resources.mailables.remember_to_return'))
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

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('filament-resources.database_notifications.territory_assigned_title', ['territory' => $this->assignment->territory->name]))
            ->body(__('filament-resources.database_notifications.territory_assigned_body', ['territory' => $this->assignment->territory->name]))
            ->icon(Heroicon::OutlinedMap)
            ->actions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(TerritoryAssignmentResource::getUrl('view', [
                        'record' => $this->assignment->id,
                    ]))
                    ->label(__('filament-resources.database_notifications.action_view')),
            ])
            ->getDatabaseMessage();
    }
}
