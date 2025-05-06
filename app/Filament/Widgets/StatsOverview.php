<?php

namespace App\Filament\Widgets;

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Illuminate\Support\Facades\Log;

class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $trend = Trend::query(
            TerritoryAssignment::query()
                ->with('territory')
        )
            ->dateColumn('started_at')
            ->between(now()->subDays(365), now())
            ->perMonth()
            ->average('DATEDIFF(ended_at, started_at)');

        return [
            Stat::make('Territori', Territory::count())
                ->description('Numero totale di territori'),
            Stat::make('Durata media assegnazioni mensili', round($trend->average('aggregate'), 2) . ' giorni')
                ->description('(ultimi 365 giorni)')
                ->chart($trend->map(fn ($item) => (float) $item->aggregate)->toArray()),
        ];
    }
}
