<?php

namespace App\Filament\Resources\Tags\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Tags\TagResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
