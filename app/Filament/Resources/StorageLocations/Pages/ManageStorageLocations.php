<?php

namespace App\Filament\Resources\StorageLocations\Pages;

use App\Filament\Exports\StorageLocationExporter;
use App\Filament\Imports\StorageLocationImporter;
use App\Filament\Resources\Concerns\HasImportExportActions;
use App\Filament\Resources\StorageLocations\StorageLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStorageLocations extends ManageRecords
{
    use HasImportExportActions;

    protected static string $resource = StorageLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getImportExportActions(StorageLocationImporter::class, StorageLocationExporter::class),
            CreateAction::make(),
        ];
    }
}
