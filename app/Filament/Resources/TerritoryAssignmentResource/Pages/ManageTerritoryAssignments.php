<?php

namespace App\Filament\Resources\TerritoryAssignmentResource\Pages;

use App\Filament\Resources\TerritoryAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTerritoryAssignments extends ManageRecords
{
    protected static string $resource = TerritoryAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
