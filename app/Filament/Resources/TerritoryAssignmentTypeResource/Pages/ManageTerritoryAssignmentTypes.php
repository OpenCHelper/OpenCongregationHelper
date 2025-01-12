<?php

namespace App\Filament\Resources\TerritoryAssignmentTypeResource\Pages;

use App\Filament\Resources\TerritoryAssignmentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTerritoryAssignmentTypes extends ManageRecords
{
    protected static string $resource = TerritoryAssignmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
