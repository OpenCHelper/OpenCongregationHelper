<?php

namespace App\Filament\Resources\Territories\Pages;

use Filament\Schemas\Schema;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\Territories\TerritoryResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use Livewire\Livewire;

class ManageTerritoryAssignments extends ManageRelatedRecords
{
    protected static string $resource = TerritoryResource::class;

    protected static string $relationship = 'assignments';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_territory_assignments');
    }

    public function form(Schema $schema): Schema
    {
        return TerritoryAssignmentResource::form($schema);
    }

    // Source: https://github.com/filamentphp/filament/discussions/10168#discussioncomment-10923902
    public static function getNavigationBadge(): ?string
    {
        return Livewire::current()->getRecord()->assignments->count();
    }

    public function table(Table $table): Table
    {
        return TerritoryAssignmentResource::table($table)
            ->defaultSort('started_at', 'desc');
    }
}
