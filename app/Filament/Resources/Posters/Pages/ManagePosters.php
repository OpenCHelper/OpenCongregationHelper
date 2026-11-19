<?php

namespace App\Filament\Resources\Posters\Pages;

use App\Filament\Resources\Posters\PosterResource;
use App\Filament\Resources\StorageLocations\StorageLocationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePosters extends ManageRecords
{
    protected static string $resource = PosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('locations')
                ->label(__('filament-resources.poster.action_manage_locations'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->url(StorageLocationResource::getUrl())
                ->visible(StorageLocationResource::canViewAny()),
            CreateAction::make(),
        ];
    }
}
