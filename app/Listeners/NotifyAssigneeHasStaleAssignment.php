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

        // Virtual users (who cannot log in) are not notified: the notification links into a panel they cannot open
        if ($assignment->assignee === null || $assignment->assignee->isVirtual()) {
            return;
        }

        $assignment->assignee->notify(new StaleAssignmentNotification($assignment));
    }
}
