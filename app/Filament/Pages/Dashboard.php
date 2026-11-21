<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TerritoryCoverage;
use App\Filament\Widgets\TerritoryGlobalMap;
use Filament\Support\Icons\Heroicon;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Home;

    // protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            TerritoryCoverage::class,
            TerritoryGlobalMap::class,
        ];
    }
}
