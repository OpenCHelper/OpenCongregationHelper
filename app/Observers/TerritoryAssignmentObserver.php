<?php

namespace App\Observers;

use App\Models\TerritoryAssignment;
use App\Notifications\TerritoryAssignedNotification;
use App\Notifications\TerritoryReturnedNotification;

class TerritoryAssignmentObserver
{
    /**
     * Handle the TerritoryAssignment "created" event.
     */
    public function created(TerritoryAssignment $territoryAssignment): void
    {
        // Carica la relazione territory prima di inviare la notifica
        $territoryAssignment->load('territory');
        $territoryAssignment->assignee->notify(new TerritoryAssignedNotification($territoryAssignment));
    }

    /**
     * Handle the TerritoryAssignment "updated" event.
     */
    public function updated(TerritoryAssignment $territoryAssignment): void
    {
        if ($territoryAssignment->wasChanged('ended_at') && $territoryAssignment->ended_at !== null) {
            // Carica la relazione territory prima di inviare la notifica
            $territoryAssignment->load(['territory', 'shared']);
            $territoryAssignment->assignee->notify(new TerritoryReturnedNotification($territoryAssignment));

            // Remove shared URL when assignment ends
            if ($territoryAssignment->shared) {
                $territoryAssignment->shared->delete();
            }
        }
    }

    /**
     * Handle the TerritoryAssignment "deleted" event.
     */
    public function deleted(TerritoryAssignment $territoryAssignment): void
    {
        // Remove shared URL when assignment is deleted
        if ($territoryAssignment->shared) {
            $territoryAssignment->shared->delete();
        }
    }

    /**
     * Handle the TerritoryAssignment "restored" event.
     */
    public function restored(TerritoryAssignment $territoryAssignment): void
    {
        //
    }

    /**
     * Handle the TerritoryAssignment "force deleted" event.
     */
    public function forceDeleted(TerritoryAssignment $territoryAssignment): void
    {
        // Remove shared URL when assignment is force deleted
        if ($territoryAssignment->shared) {
            $territoryAssignment->shared->delete();
        }
    }
}
