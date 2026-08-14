<?php

namespace App\Filament\Resources\TerritoryAssignments\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use Filament\Actions;
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
