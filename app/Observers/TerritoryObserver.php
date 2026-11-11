<?php

namespace App\Observers;

use App\Models\Territory;

class TerritoryObserver
{
    /**
     * Handle the Territory "created" event.
     */
    public function created(Territory $territory): void
    {
        //
    }

    /**
     * Handle the Territory "updated" event.
     */
    public function updated(Territory $territory): void
    {
        //
    }

    /**
     * Handle the Territory "deleted" event.
     */
    public function deleted(Territory $territory): void
    {
        //
    }

    /**
     * Handle the Territory "restored" event.
     */
    public function restored(Territory $territory): void
    {
        //
    }

    /**
     * Handle the Territory "force deleted" event.
     */
    public function forceDeleted(Territory $territory): void
    {
        //
    }
}
