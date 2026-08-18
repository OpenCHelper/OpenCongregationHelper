<?php

namespace App\Jobs;

use App\Events\StaleAssignmentDetected;
use App\Models\TerritoryAssignment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
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

        $assignments = TerritoryAssignment::where('ended_at', null)->get();

        foreach ($assignments as $assignment) {
            $elapsedTime = Carbon::now()->diffInDays($assignment->started_at);

            // Date is in the future
            if ($elapsedTime > 0) {
                continue;
            }

            // Get the stale level whose key is higher than the elapsed time
            $staleLevel = collect($this->getStaleLevels())
                ->sortKeys()
                ->filter(function ($level, $key) use ($elapsedTime) {
                    return abs($elapsedTime) > $key;
                })
                ->last();

            // No stale level found
            if ($staleLevel === null) {
                continue;
            }

            StaleAssignmentDetected::dispatch($assignment);
        }

        Log::info('DetectStaleAssignments job completed.');
    }
}
