<?php

namespace App\Observers;

use App\Models\TerritoryAssignment;
use Illuminate\Support\Facades\Log;

use App\Notifications\TerritoryAssignedNotification;
use App\Notifications\TerritoryReturnedNotification;

class TerritoryAssignmentObserver
{
    /**
     * Handle the TerritoryAssignment "created" event.
     */
    public function created(TerritoryAssignment $territoryAssignment): void
    {
        Log::info('TerritoryAssignment created', [
            'territory_assignment' => $territoryAssignment,
        ]);

        Log::info("User preferred locale: " . $territoryAssignment->assignee->preferredLocale());
        
        // Carica la relazione territory prima di inviare la notifica
        $territoryAssignment->load('territory');
        $territoryAssignment->assignee->notify(new TerritoryAssignedNotification($territoryAssignment));
    }

    /**
     * Handle the TerritoryAssignment "updated" event.
     */         
    public function updated(TerritoryAssignment $territoryAssignment): void
    {
        Log::info('TerritoryAssignment updated', [
            'territory_assignment' => $territoryAssignment,
        ]);
        
        if ($territoryAssignment->isDirty('ended_at') && $territoryAssignment->ended_at !== null) {
            // Carica la relazione territory prima di inviare la notifica
            $territoryAssignment->load('territory');
            $territoryAssignment->assignee->notify(new TerritoryReturnedNotification($territoryAssignment));
        }
    }

    /**
     * Handle the TerritoryAssignment "deleted" event.
     */
    public function deleted(TerritoryAssignment $territoryAssignment): void
    {
        //
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
        //
    }
}
