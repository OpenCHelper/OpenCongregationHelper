<?php

namespace App\Notifications;

use App\Models\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use Illuminate\Support\Carbon;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action as FilamentAction;
use App\Filament\Resources\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;

class StaleAssignmentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TerritoryAssignment $assignment
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
            ->subject('Avviso territorio in scadenza')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('il territorio ' . $this->assignment->with("territory")->firstOrFail()->territory->name . ' risulta assegnato a te da ' . round($this->assignment->started_at->diffInDays(Carbon::now(), absolute: true)) . ' giorni.')
            ->line('Se lo hai terminato, ricordati di riconsegnarlo.')
            ->action('Vedi territorio', TerritoryAssignmentResource::getUrl('view', [
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
        $diffDays = round(Carbon::now()->diffInDays($this->assignment->started_at, absolute: true));

        return FilamentNotification::make()
            ->title('Territorio fuori da ' . $diffDays . ' giorni')
            ->body('Se lo hai terminato, ricordati di riconsegnarlo.')
            ->icon('heroicon-o-map')
            ->warning()
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
