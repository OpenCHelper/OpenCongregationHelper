<?php

namespace App\Filament\Resources\TerritoryZoneResource\Pages;

use App\Filament\Resources\TerritoryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTerritoryZone extends EditRecord
{
    protected static string $resource = TerritoryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
