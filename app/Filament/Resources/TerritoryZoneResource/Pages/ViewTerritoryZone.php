<?php

namespace App\Filament\Resources\TerritoryZoneResource\Pages;

use App\Filament\Resources\TerritoryZoneResource;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;

class ViewTerritoryZone extends ViewRecord
{
    protected static string $resource = TerritoryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
