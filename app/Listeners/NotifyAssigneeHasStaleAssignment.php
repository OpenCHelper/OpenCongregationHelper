<?php

namespace App\Listeners;

use App\Events\StaleAssignmentDetected;
use App\Filament\Resources\TerritoryResource;
use App\Notifications\StaleAssignmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyAssigneeHasStaleAssignment
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(StaleAssignmentDetected $event): void
    {
        // Notify the assignee about the stale assignment
        $assignment = $event->assignment;

        // Here you can send a notification to the assignee
        // For example, using Laravel's notification system:
        // $assignment->assignee->notify(new StaleAssignmentNotification($assignment));

        $diffDays = round(Carbon::now()->diffInDays($assignment->date_start, absolute: true));

        Log::info('NotifyAssigneeHasStaleAssignment listener triggered.', [
            'assignmee' => $assignment->assignee,
            'assignment' => $assignment,
        ]);
        // Log the notification for debugging purposes
        $assignment->assignee->notify(new StaleAssignmentNotification($assignment));
    }
}
