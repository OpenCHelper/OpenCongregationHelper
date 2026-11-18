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
     * The levels of the assignments in progress for too long, keyed by name, with the days after which they start (see
     * the Settings page).
     *
     * @return array<string, array{days: int, description: string, color: string}>
     */
    public function getStaleLevels(): array
    {
        $days = TerritorySettings::values('stale_after_days', 'dead_after_days');

        return [
            'stale' => [
                'days' => $days['stale_after_days'],
                'description' => 'Assignment is stale',
                'color' => 'yellow',
            ],
            'dead' => [
                'days' => $days['dead_after_days'],
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
        $staleAfterDays = min(array_column($this->getStaleLevels(), 'days'));

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
