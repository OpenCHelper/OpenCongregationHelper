<?php

namespace App\Filament\Resources\TerritoryAssignments\Pages;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTerritoryAssignments extends ManageRecords
{
    protected static string $resource = TerritoryAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
