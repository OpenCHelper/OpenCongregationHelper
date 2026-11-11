<?php

namespace App\Jobs;

use App\Events\StaleAssignmentDetected;
use App\Models\TerritoryAssignment;
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
            60 => [
                'label' => 'stale',
                'description' => 'Assignment is stale',
                'color' => 'yellow',
            ],
            90 => [
                'label' => 'very_stale',
                'description' => 'Assignment is very stale',
                'color' => 'orange',
            ],
            120 => [
                'label' => 'extremely_stale',
                'description' => 'Assignment is extremely stale',
                'color' => 'red',
            ],
            150 => [
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
            ->whereNull('ended_at')
            ->where('started_at', '<', now()->subDays($staleAfterDays))
            ->lazyById()
            ->each(fn (TerritoryAssignment $assignment) => StaleAssignmentDetected::dispatch($assignment));

        Log::info('DetectStaleAssignments job completed.');
    }
}
