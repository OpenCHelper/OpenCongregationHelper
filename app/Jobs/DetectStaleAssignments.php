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

    public function getStaleLevels(): array
    {
        return [
            TerritorySettings::value('stale_after_days') => [
                'label' => 'stale',
                'description' => 'Assignment is stale',
                'color' => 'yellow',
            ],
            TerritorySettings::value('very_stale_after_days') => [
                'label' => 'very_stale',
                'description' => 'Assignment is very stale',
                'color' => 'orange',
            ],
            TerritorySettings::value('extremely_stale_after_days') => [
                'label' => 'extremely_stale',
                'description' => 'Assignment is extremely stale',
                'color' => 'red',
            ],
            TerritorySettings::value('dead_after_days') => [
                'label' => 'dead',
                'description' => 'Assignment is dead',
                'color' => 'black',
            ],
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('DetectStaleAssignments job started.');

        // An assignment is stale when it has been open for longer than the lowest stale level
        $staleAfterDays = min(array_keys($this->getStaleLevels()));

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
