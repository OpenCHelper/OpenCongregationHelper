<?php

namespace App\Filament\Resources\TerritoryZoneResource\Pages;

use App\Filament\Resources\TerritoryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTerritoryZones extends ListRecords
{
    protected static string $resource = TerritoryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
