<?php

namespace App\Filament\Resources\ServiceGroups\Pages;

use App\Filament\Resources\ServiceGroups\ServiceGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceGroup extends ViewRecord
{
    protected static string $resource = ServiceGroupResource::class;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.view_service_group');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
