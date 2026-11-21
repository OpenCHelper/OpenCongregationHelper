<?php

namespace App\Jobs;

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Events\StaleAssignmentDetected;
use App\Models\TerritoryAssignment;
use App\Models\User;
use App\Notifications\StaleAssignmentsDepartmentNotification;
use App\Settings\TerritorySettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        $staleAssignments = new Collection;

        TerritoryAssignment::query()
            // Skip the assignments whose territory (or its zone) has been deleted while still assigned
            ->whereHas('territory')
            ->whereNull('ended_at')
            ->where('started_at', '<', now()->subDays($staleAfterDays))
            ->lazyById()
            ->each(function (TerritoryAssignment $assignment) use ($staleAssignments): void {
                StaleAssignmentDetected::dispatch($assignment);
                $staleAssignments->push($assignment);
            });

        $this->notifyTerritoryDepartment($staleAssignments, $staleAfterDays);

        Log::info('DetectStaleAssignments job completed.');
    }

    /**
     * Send the members of the territory department (who can log in) a summary of the stale assignments, so that they
     * can follow up with the assignees: all of them, or only the overseers (see the Settings page).
     *
     * @param  Collection<int, TerritoryAssignment>  $staleAssignments
     */
    protected function notifyTerritoryDepartment(Collection $staleAssignments, int $staleAfterDays): void
    {
        if ($staleAssignments->isEmpty()) {
            return;
        }

        $members = User::query()
            ->inDepartment(
                DepartmentKey::Territories,
                TerritorySettings::value('stale_summary_overseers_only') ? DepartmentRole::Overseer : null,
            )
            ->where('can_login', true)
            ->get();

        Notification::send($members, new StaleAssignmentsDepartmentNotification($staleAssignments, $staleAfterDays));
    }
}
