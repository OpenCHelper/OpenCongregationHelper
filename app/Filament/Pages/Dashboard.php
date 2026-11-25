<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NoDashboardAccess;
use Filament\Support\Icons\Heroicon;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Home;

    // protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        // Each widget checks who can see it: the last one is shown only to who sees none of the others
        return [
            ...NoDashboardAccess::DASHBOARD_WIDGETS,
            NoDashboardAccess::class,
        ];
    }
}
