<?php

namespace App\Observers;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Support\MapCenter;

class TerritoryObserver
{
    /**
     * Handle the Territory "created" event.
     */
    public function created(Territory $territory): void
    {
        MapCenter::forget();
    }

    /**
     * Handle the Territory "updated" event.
     */
    public function updated(Territory $territory): void
    {
        MapCenter::forget();
    }

    /**
     * Handle the Territory "deleting" event, fired by both soft and force deletes.
     *
     * A territory that is still assigned cannot be deleted: its assignment in progress
     * (and its public link) would be left pointing to a territory that can no longer
     * be loaded. The territory must be returned first.
     *
     * @return false|null false cancels the deletion; null lets it (and the other "deleting" listeners) go on
     */
    public function deleting(Territory $territory): ?bool
    {
        if (TerritoryAssignment::territoryHasOpenAssignment($territory->getKey())) {
            return false;
        }

        return null;
    }

    /**
     * Handle the Territory "deleted" event.
     */
    public function deleted(Territory $territory): void
    {
        MapCenter::forget();
    }

    /**
     * Handle the Territory "restored" event.
     */
    public function restored(Territory $territory): void
    {
        MapCenter::forget();
    }

    /**
     * Handle the Territory "force deleted" event.
     */
    public function forceDeleted(Territory $territory): void
    {
        MapCenter::forget();
    }
}
