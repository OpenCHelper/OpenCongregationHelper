<?php

namespace App\Filament\Resources\Territories\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use App\Filament\Resources\TerritoryAssignments\Actions\CreateAssignmentAction;
use App\Filament\Resources\TerritoryAssignments\Actions\ReturnAssignmentAction;
use App\Filament\Resources\Territories\TerritoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

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
