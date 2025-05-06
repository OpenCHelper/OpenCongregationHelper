<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Models\User;
use App\Models\TerritoryAssignment;
use App\Filament\Resources\TerritoryAssignmentResource;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action as FilamentAction;

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
            ->subject('Territorio ' . $this->assignment->territory->name . ' assegnato')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Ti è stato assegnato il territorio ' . $this->assignment->territory->name . '.')
            ->line('Ricordati di consegnarlo una volta percorso.')
            ->action('Vedi dettagli', TerritoryAssignmentResource::getUrl('view', [
                'record' => $this->assignment->id,
            ]))
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
            ->title('Territorio ' . $this->assignment->territory->name . ' assegnato')
            ->body('Ti è stato assegnato il territorio ' . $this->assignment->territory->name . '.')
            ->icon('heroicon-o-map')
            ->actions([
                FilamentAction::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(TerritoryAssignmentResource::getUrl('view', [
                        'record' => $this->assignment->id,
                    ]))
                    ->label('Vedi'),
            ])
            ->getDatabaseMessage();
    }
}
