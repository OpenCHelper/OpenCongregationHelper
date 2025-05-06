<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Models\User;
use App\Models\TerritoryAssignmentType;
use App\Filament\Resources\TerritoryAssignmentTypeResource;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action as FilamentAction;

class CampaignCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TerritoryAssignmentType $assignmentType,
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
            ->subject('Nuova campagna!')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('E\' stata creata una nuova campagna: ' . $this->assignmentType->name)
            ->line('Ti ringraziamo per il tuo impegno!');
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
            ->title('Nuova campagna!')
            ->body('E\' stata creata una nuova campagna: ' . $this->assignmentType->name)
            ->icon('heroicon-o-megaphone')
            ->actions([])
            ->getDatabaseMessage();
    }
}
