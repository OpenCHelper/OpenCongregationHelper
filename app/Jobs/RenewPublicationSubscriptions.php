<?php

namespace App\Jobs;

use App\Models\PublicationSubscription;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Request again the publications received regularly whose period is due (e.g. The Watchtower every month).
 *
 * Run every day and safe to run again: a missed day is caught up by the next run, and no period is requested twice.
 */
class RenewPublicationSubscriptions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $made = 0;

        PublicationSubscription::query()
            ->active()
            // Only the publications still offered: the ones turned off are no longer shown in the regular publications
            ->whereHas('publication', fn ($query) => $query->where('renewal_months', '>', 0)->where('is_active', true))
            ->with(['publication', 'subscriber'])
            ->each(function (PublicationSubscription $subscription) use (&$made): void {
                $made += count($subscription->renew());
            });

        Log::info("Renewed the publications received regularly: {$made} requests made.");
    }
}
