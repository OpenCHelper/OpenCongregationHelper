<?php

namespace App\Support;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The data of the Territory Assignment Record (S-13) of a service year.
 *
 * A service year runs from September 1st to August 31st and is named after the year in which it ends
 * (e.g. the 2026 service year runs from 2025-09-01 to 2026-08-31).
 */
class TerritoryAssignmentRecord
{
    /**
     * The month in which a service year starts.
     */
    public const SERVICE_YEAR_START_MONTH = 9;

    /**
     * The service year that includes the given date (today by default).
     */
    public static function serviceYearOf(?Carbon $date = null): int
    {
        $date ??= Carbon::today();

        return $date->month >= self::SERVICE_YEAR_START_MONTH ? $date->year + 1 : $date->year;
    }

    /**
     * The first day of the given service year.
     */
    public static function serviceYearStart(int $serviceYear): Carbon
    {
        return Carbon::create($serviceYear - 1, self::SERVICE_YEAR_START_MONTH, 1)->startOfDay();
    }

    /**
     * The last moment of the given service year.
     */
    public static function serviceYearEnd(int $serviceYear): Carbon
    {
        return self::serviceYearStart($serviceYear + 1)->subSecond();
    }

    /**
     * The rows of the record: one per territory, sorted by name, with the date on which it was last completed before
     * the service year and the assignments of the service year (those started in it, and those still in progress
     * when it started), oldest first.
     *
     * @return Collection<int, array{territory: string, last_completed_at: ?Carbon, assignments: list<array{assignee: string, assigned_at: Carbon, completed_at: ?Carbon}>}>
     */
    public static function rows(int $serviceYear): Collection
    {
        $start = self::serviceYearStart($serviceYear);
        $end = self::serviceYearEnd($serviceYear);

        return Territory::query()
            ->withMax(['assignments as last_completed_at' => fn ($query) => $query->where('ended_at', '<', $start)], 'ended_at')
            ->with(['assignments' => fn ($query) => $query
                ->where('started_at', '<=', $end)
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $start))
                ->oldest('started_at'),
            ])
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Territory $territory): array => [
                'territory' => $territory->name,
                'last_completed_at' => $territory->last_completed_at !== null ? Carbon::parse($territory->last_completed_at) : null,
                'assignments' => $territory->assignments
                    ->map(fn (TerritoryAssignment $assignment): array => [
                        'assignee' => $assignment->assignee?->name ?? '',
                        'assigned_at' => $assignment->started_at,
                        // Returned after the end of the service year: still in progress in this record
                        'completed_at' => $assignment->ended_at?->lte($end) ? $assignment->ended_at : null,
                    ])
                    ->all(),
            ]);
    }
}
