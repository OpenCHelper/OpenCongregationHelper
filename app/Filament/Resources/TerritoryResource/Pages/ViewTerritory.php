<?php

namespace App\Filament\Resources\TerritoryResource\Pages;

use App\Filament\Actions\Assignment\CreateAssignmentAction;
use App\Filament\Actions\Assignment\DeleteAssignmentAction;
use App\Filament\Resources\TerritoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTerritory extends ViewRecord
{
    protected static string $resource = TerritoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAssignmentAction::make('create_assignment'),
            DeleteAssignmentAction::make('delete_assignment'),
            Actions\ActionGroup::make([
                Actions\EditAction::make(),
            ]),
        ];
    }
}
