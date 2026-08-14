<?php

namespace App\Filament\Widgets;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $durationExpr = match (DB::connection()->getDriverName()) {
            'sqlite' => 'julianday(date(ended_at)) - julianday(date(started_at))',
            'pgsql' => '(ended_at::date - started_at::date)',
            'sqlsrv' => 'DATEDIFF(day, started_at, ended_at)',
            default => 'DATEDIFF(ended_at, started_at)',
        };

        $trend = Trend::query(
            TerritoryAssignment::query()
                ->with('territory')
        )
            ->dateColumn('started_at')
            ->between(now()->subDays(365), now())
            ->perMonth()
            ->average($durationExpr);

        return [
            Stat::make(__('filament-resources.stats_widget.territories'), Territory::count())
                ->description(__('filament-resources.stats_widget.total_territories_description')),
            Stat::make(__('filament-resources.stats_widget.average_assignment_duration'), round($trend->average('aggregate'), 2).' '.__('filament-resources.stats_widget.days'))
                ->description(__('filament-resources.stats_widget.last_365_days'))
                ->chart($trend->map(fn ($item) => (float) $item->aggregate)->toArray()),
        ];
    }
}
