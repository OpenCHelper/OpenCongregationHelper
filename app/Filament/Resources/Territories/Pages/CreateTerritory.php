<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTerritory extends CreateRecord
{
    protected static string $resource = TerritoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TerritoryResource::mergeUploadedImageIntoFormData($data);
    }
}
