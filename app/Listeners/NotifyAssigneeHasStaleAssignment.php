<?php

namespace App\Listeners;

use App\Events\StaleAssignmentDetected;
use App\Notifications\StaleAssignmentNotification;

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
        $assignment = $event->assignment;

        // Notify the assignee about the stale assignment
        $assignment->assignee->notify(new StaleAssignmentNotification($assignment));
    }
}
