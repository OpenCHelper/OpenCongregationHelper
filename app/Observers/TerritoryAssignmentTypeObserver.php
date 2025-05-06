<?php

namespace App\Observers;

use App\Models\TerritoryAssignmentType;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CampaignCreatedNotification;

class TerritoryAssignmentTypeObserver
{
    /**
     * Handle the TerritoryAssignmentType "created" event.
     */
    public function created(TerritoryAssignmentType $territoryAssignmentType): void
    {
        Notification::send(User::all(), new CampaignCreatedNotification($territoryAssignmentType));
    }

    /**
     * Handle the TerritoryAssignmentType "updated" event.
     */
    public function updated(TerritoryAssignmentType $territoryAssignmentType): void
    {
        //
    }

    /**
     * Handle the TerritoryAssignmentType "deleted" event.
     */
    public function deleted(TerritoryAssignmentType $territoryAssignmentType): void
    {
        //
    }

    /**
     * Handle the TerritoryAssignmentType "restored" event.
     */
    public function restored(TerritoryAssignmentType $territoryAssignmentType): void
    {
        //
    }

    /**
     * Handle the TerritoryAssignmentType "force deleted" event.
     */
    public function forceDeleted(TerritoryAssignmentType $territoryAssignmentType): void
    {
        //
    }
}
