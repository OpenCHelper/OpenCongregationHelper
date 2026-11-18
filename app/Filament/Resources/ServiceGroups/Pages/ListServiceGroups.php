<?php

namespace App\Filament\Resources\ServiceGroups\Pages;

use App\Filament\Resources\ServiceGroups\ServiceGroupResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListServiceGroups extends ListRecords
{
    protected static string $resource = ServiceGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('organize')
                ->label(__('filament-pages.organize_service_groups'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->url(ServiceGroupResource::getUrl('organize'))
                ->visible(OrganizeServiceGroups::canAccess()),
            CreateAction::make(),
        ];
    }
}
