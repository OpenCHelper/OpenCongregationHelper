<?php

namespace App\Filament\Widgets;

use App\Models\Territory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        return [
            Stat::make('Territori', Territory::count())
                ->description('Numero totale di territori'),
        ];
    }
}
