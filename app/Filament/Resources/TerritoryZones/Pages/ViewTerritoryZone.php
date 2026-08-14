<?php

namespace App\Filament\Resources\TerritoryZones\Pages;

use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;

class ViewTerritoryZone extends ViewRecord
{
    protected static string $resource = TerritoryZoneResource::class;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.view_territory_zone');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
