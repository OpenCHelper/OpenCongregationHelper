<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TerritoryGlobalMap;

class Dashboard extends \Filament\Pages\Dashboard
{
    // protected static ?string $navigationIcon = 'heroicon-o-document-text';

    // protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array {
        return [
            TerritoryGlobalMap::class,
        ];
    }
}
