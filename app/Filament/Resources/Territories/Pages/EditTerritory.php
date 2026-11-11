<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use Exception;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTerritory extends EditRecord
{
    protected static string $resource = TerritoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['image_path'], $data['image_file'])) {
            throw new Exception("Form 'image_path' and 'image_file' cannot be set at the same time");
        }

        if (isset($data['image_file'])) {
            $data['image_path'] = $data['image_file'];
        }

        unset($data['image_file']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['image_path'], $data['image_file'])) {
            throw new Exception("Form 'image_path' and 'image_file' cannot be set at the same time");
        }

        if (isset($data['image_file'])) {
            $data['image_path'] = $data['image_file'];
        }

        unset($data['image_file']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
