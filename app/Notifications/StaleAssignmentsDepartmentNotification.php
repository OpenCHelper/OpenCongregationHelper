<?php

namespace App\Notifications;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * The weekly summary of the assignments out for too long, sent by email and in the app to the members of the territory
 * department so that they can follow up with the assignees.
 */
class StaleAssignmentsDepartmentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, TerritoryAssignment>  $assignments
     */
    public function __construct(
        public Collection $assignments,
        public int $staleAfterDays,
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
        $channels = ['mail', 'database'];

        if ($this->isWebPushAvailable($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification: one line per assignment, the oldest first.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('filament-resources.mailables.stale_assignments_department_subject', [
                'count' => $this->assignments->count(),
            ]))
            ->greeting(__('filament-resources.mailables.greeting', ['name' => $notifiable->name]))
            ->line(__('filament-resources.mailables.stale_assignments_department_body', [
                'days' => $this->staleAfterDays,
            ]));

        $this->assignments
            ->loadMissing(['territory', 'assignee'])
            ->sortBy('started_at')
            ->each(fn (TerritoryAssignment $assignment) => $mail->line(
                __('filament-resources.mailables.stale_assignments_department_line', [
                    'territory' => $assignment->territory->name,
                    'assignee' => $assignment->assignee->name,
                    'days' => round($assignment->started_at->diffInDays(Carbon::now(), absolute: true)),
                ]),
            ));

        return $mail
            ->action(__('filament-resources.mailables.view_assignments'), TerritoryAssignmentResource::getUrl('index'))
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
            ->title(__('filament-resources.database_notifications.stale_assignments_department_title', [
                'count' => $this->assignments->count(),
            ]))
            ->body(__('filament-resources.database_notifications.stale_assignments_department_body', [
                'days' => $this->staleAfterDays,
            ]))
            ->icon(Heroicon::OutlinedMap)
            ->warning()
            ->actions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(TerritoryAssignmentResource::getUrl('index'))
                    ->label(__('filament-resources.database_notifications.action_view')),
            ])
            ->getDatabaseMessage();
    }
}
