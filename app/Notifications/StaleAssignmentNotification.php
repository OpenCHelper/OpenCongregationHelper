<?php

namespace App\Notifications;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class StaleAssignmentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TerritoryAssignment $assignment
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
            ->subject(__('filament-resources.mailables.stale_assignment_subject'))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.stale_assignment_body', [
                'territory' => $this->assignment->loadMissing('territory')->territory->name,
                'days' => round($this->assignment->started_at->diffInDays(Carbon::now(), absolute: true)),
            ]))
            ->line(__('filament-resources.mailables.remember_to_return_if_finished'))
            ->action(__('filament-resources.mailables.view_territory'), TerritoryAssignmentResource::getUrl('view', [
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
        $diffDays = round(Carbon::now()->diffInDays($this->assignment->started_at, absolute: true));

        return FilamentNotification::make()
            ->title(__('filament-resources.database_notifications.stale_assignment_title', ['days' => $diffDays]))
            ->body(__('filament-resources.database_notifications.stale_assignment_body'))
            ->icon(Heroicon::OutlinedMap)
            ->warning()
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
