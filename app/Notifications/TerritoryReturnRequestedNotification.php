<?php

namespace App\Notifications;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use App\Notifications\Concerns\SendsWebPush;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Sent to the territory department when the return of an assignment is asked (by its assignee, or from its shared
 * link), so that they close it.
 */
class TerritoryReturnRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TerritoryAssignment $assignment,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if ($this->isWebPushAvailable($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('filament-resources.mailables.territory_return_requested_subject', ['territory' => $this->getTerritoryName()]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.territory_return_requested_body', [
                'territory' => $this->getTerritoryName(),
                'assignee' => $this->assignment->assignee?->full_name,
            ]))
            ->action(__('filament-resources.mailables.view_details'), $this->getAssignmentUrl());
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
            ->title(__('filament-resources.database_notifications.territory_return_requested_title', ['territory' => $this->getTerritoryName()]))
            ->body(__('filament-resources.database_notifications.territory_return_requested_body', [
                'assignee' => $this->assignment->assignee?->full_name,
            ]))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->info()
            ->actions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->url($this->getAssignmentUrl())
                    ->label(__('filament-resources.database_notifications.action_view')),
            ])
            ->getDatabaseMessage();
    }

    /**
     * The name of the territory, or a placeholder when it has been deleted while still assigned.
     */
    private function getTerritoryName(): string
    {
        return $this->assignment->loadMissing('territory')->territory?->name
            ?? __('filament-resources.territory_assignment.info_territory_placeholder');
    }

    private function getAssignmentUrl(): string
    {
        return TerritoryAssignmentResource::getUrl('view', ['record' => $this->assignment->id], panel: 'app');
    }
}
