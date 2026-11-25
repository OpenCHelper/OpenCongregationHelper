<?php

namespace App\Filament\Widgets;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '10s';

    /**
     * Only who can view the territories sees their statistics.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Territory::class) ?? false;
    }

    protected function getStats(): array
    {
        $durationExpr = TerritoryAssignment::durationInDaysExpression();

        $from = now()->subDays(365);
        $to = now();

        $averageDuration = $this->returnedAssignments()
            ->whereBetween('ended_at', [$from, $to])
            ->avg(DB::raw($durationExpr));

        // Monthly averages, only for the chart: empty months are shown as 0
        $trend = Trend::query($this->returnedAssignments())
            ->dateColumn('ended_at')
            ->between($from, $to)
            ->perMonth()
            ->average($durationExpr);

        return [
            Stat::make(__('filament-resources.stats_widget.territories'), Territory::count())
                ->description(__('filament-resources.stats_widget.total_territories_description')),
            Stat::make(
                __('filament-resources.stats_widget.average_assignment_duration'),
                $averageDuration !== null ? round((float) $averageDuration, 2).' '.__('filament-resources.stats_widget.days') : '—',
            )
                ->description(__('filament-resources.stats_widget.last_365_days'))
                ->chart($trend->map(fn ($item) => (float) $item->aggregate)->toArray()),
        ];
    }

    /**
     * @return Builder<TerritoryAssignment>
     */
    private function returnedAssignments(): Builder
    {
        return TerritoryAssignment::query()->returned();
    }
}
