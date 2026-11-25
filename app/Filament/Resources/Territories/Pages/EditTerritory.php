<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTerritory extends EditRecord
{
    protected static string $resource = TerritoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return TerritoryResource::splitStoredImageFromFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TerritoryResource::mergeUploadedImageIntoFormData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            TerritoryResource::explainRefusedDeletion(DeleteAction::make()),
        ];
    }
}
