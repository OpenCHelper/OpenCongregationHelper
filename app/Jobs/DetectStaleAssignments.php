<?php

namespace App\Jobs;

use App\Events\StaleAssignmentDetected;
use App\Models\TerritoryAssignment;
use App\Settings\TerritorySettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DetectStaleAssignments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('DetectStaleAssignments job started.');

        // An assignment is stale when it has been open for longer than the days set on the Settings page
        $staleAfterDays = TerritorySettings::value('stale_after_days');

        TerritoryAssignment::query()
            // Skip the assignments whose territory (or its zone) has been deleted while still assigned
            ->whereHas('territory')
            ->whereNull('ended_at')
            ->where('started_at', '<', now()->subDays($staleAfterDays))
            ->lazyById()
            ->each(fn (TerritoryAssignment $assignment) => StaleAssignmentDetected::dispatch($assignment));

        Log::info('DetectStaleAssignments job completed.');
    }
}
