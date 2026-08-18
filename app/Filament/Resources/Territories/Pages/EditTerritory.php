<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use Exception;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditTerritory extends EditRecord
{
    protected static string $resource = TerritoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['image_path'] = $data['image_file'] ?? null;

        Log::info('Mutating form data before create', [
            'data' => $data,
            'image_path' => $data['image_path'] ?? 'undefined',
            'image_file' => $data['image_file'] ?? 'undefined',
        ]);
        unset($data['image_file']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['image_path'], $data['image_file'])) {
            Log::error('Image path and image file cannot be set at the same time', [
                'image_path' => $data['image_path'],
                'image_file' => $data['image_file'],
            ]);
            throw new Exception("Form 'image_path' and 'image_file' cannot be set at the same time");
        }
        Log::info('Mutating form data before save', [
            'image_path' => $data['image_path'] ?? null,
            'image_file' => $data['image_file'] ?? null,
            'data' => $data,
        ]);

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
