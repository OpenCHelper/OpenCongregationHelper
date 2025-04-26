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
                ->where('date_start', '>=', now()->subDays(30))
        )
            ->dateColumn('date_start')
            ->between(now()->subDays(365), now())
            ->perMonth()
            ->average('DATEDIFF(date_end, date_start)');

        return [
            Stat::make('Territori', Territory::count())
                ->description('Numero totale di territori'),
            Stat::make('Media assegnazioni', '192.1k')
                ->description('Media assegnazioni globale negli ultimi 365 giorni')
                // ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($trend->map(function ($item) {
                    return (float) $item->aggregate;
                })->toArray()),
    
        ];
    }
}
