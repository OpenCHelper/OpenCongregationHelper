<?php

namespace App\Observers;

use App\Models\TerritoryAssignmentType;
use App\Models\User;
use Filament\Notifications\Notification;

class TerritoryAssignmentTypeObserver
{
    /**
     * Handle the TerritoryAssignmentType "created" event.
     */
    public function created(TerritoryAssignmentType $territoryAssignmentType): void
    {
        Notification::make()
            ->title('Nuova campagna!')
            ->body('E\' stata creata una nuova campagna: ' . $territoryAssignmentType->name)
            ->icon('heroicon-o-megaphone')
            ->actions([])
            ->sendToDatabase(User::all());
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
