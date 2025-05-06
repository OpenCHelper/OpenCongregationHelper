<?php

namespace App\Filament\Resources\TerritoryResource\Pages;

use App\Filament\Resources\TerritoryAssignmentResource;
use App\Filament\Resources\TerritoryResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use Livewire\Livewire;

class ManageTerritoryAssignments extends ManageRelatedRecords
{
    protected static string $resource = TerritoryResource::class;

    protected static string $relationship = 'assignments';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Storico uscite';
    }

    public function form(Form $form): Form
    {
        return TerritoryAssignmentResource::form($form);
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
