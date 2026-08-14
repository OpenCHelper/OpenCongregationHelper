<?php

namespace App\Filament\Resources\TerritoryZones\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTerritoryZones extends ListRecords
{
    protected static string $resource = TerritoryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
