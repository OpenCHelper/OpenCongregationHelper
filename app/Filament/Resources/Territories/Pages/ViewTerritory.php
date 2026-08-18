<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\Actions\CreateAssignmentAction;
use App\Filament\Resources\TerritoryAssignments\Actions\ReturnAssignmentAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTerritory extends ViewRecord
{
    protected static string $resource = TerritoryResource::class;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.view_territory');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAssignmentAction::make('create_assignment'),
            ReturnAssignmentAction::make('delete_assignment'),
            ActionGroup::make([
                EditAction::make(),
            ]),
        ];
    }
}
