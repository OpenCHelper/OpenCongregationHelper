<?php

namespace App\Filament\Resources\TerritoryResource\Pages;

use App\Filament\Actions\Assignment\CreateAssignmentAction;
use App\Filament\Actions\Assignment\DeleteAssignmentAction;
use App\Filament\Resources\TerritoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTerritory extends ViewRecord
{
    protected static string $resource = TerritoryResource::class;

    public static function getNavigationLabel(): string
    {
        return 'Territorio';
    }

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
