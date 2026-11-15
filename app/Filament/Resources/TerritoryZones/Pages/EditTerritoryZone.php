<?php

namespace App\Filament\Resources\TerritoryZones\Pages;

use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTerritoryZone extends EditRecord
{
    protected static string $resource = TerritoryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TerritoryZoneResource::explainRefusedDeletion(DeleteAction::make()),
        ];
    }
}
