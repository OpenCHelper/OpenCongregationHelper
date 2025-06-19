<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CampaignCreatedNotification;

class CampaignObserver
{
    /**
     * Handle the Campaign "created" event.
     */
    public function created(Campaign $campaign): void
    {
        Notification::send(User::all(), new CampaignCreatedNotification($campaign));
    }

    /**
     * Handle the Campaign "updated" event.
     */
    public function updated(Campaign $campaign): void
    {
        //
    }

    /**
     * Handle the Campaign "deleted" event.
     */
    public function deleted(Campaign $campaign): void
    {
        //
    }

    /**
     * Handle the Campaign "restored" event.
     */
    public function restored(Campaign $campaign): void
    {
        //
    }

    /**
     * Handle the Campaign "force deleted" event.
     */
    public function forceDeleted(Campaign $campaign): void
    {
        //
    }
}
