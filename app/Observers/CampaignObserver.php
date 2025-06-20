<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;
use App\Notifications\CampaignCreatedNotification;

class CampaignObserver
{
    /**
     * Handle the Campaign "creating" event.
     */
    public function creating(Campaign $campaign): void
    {
        if (!$campaign->created_by && Auth::check()) {
            $campaign->created_by = Auth::id();
        }
    }

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
