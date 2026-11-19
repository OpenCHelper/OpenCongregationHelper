<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManagePublications extends ManageRecords
{
    protected static string $resource = PublicationResource::class;

    /**
     * The name of the menu item, not the plural model label in title case.
     */
    public function getTitle(): string|Htmlable
    {
        return PublicationResource::getNavigationLabel();
    }

    public function getSubheading(): ?string
    {
        return __('filament-resources.publication.list_subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
