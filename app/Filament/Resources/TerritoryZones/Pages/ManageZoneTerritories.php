<?php

namespace App\Filament\Resources\TerritoryZones\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Livewire;

class ManageZoneTerritories extends ManageRelatedRecords
{
    protected static string $resource = TerritoryZoneResource::class;

    protected static string $relationship = 'territories';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_zone_territories');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-pages.manage_zone_territories_title', ['name' => $this->getRecordTitle()]);
    }

    public function getBreadcrumb(): string
    {
        return static::getNavigationLabel();
    }

    // Source: https://github.com/filamentphp/filament/discussions/10168#discussioncomment-10923902
    public static function getNavigationBadge(): ?string
    {
        return Livewire::current()->getRecord()->territories->count();
    }

    public function form(Schema $schema): Schema
    {
        return TerritoryResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.territory.column_name')),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                // A territory created in a deleted zone would be hidden together with the zone
                CreateAction::make()
                    ->hidden(fn (): bool => $this->getOwnerRecord()->trashed()),
                // Tables\Actions\AssociateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Tables\Actions\DissociateAction::make(),
                TerritoryResource::explainRefusedDeletion(DeleteAction::make()),
                TerritoryResource::explainRefusedDeletion(ForceDeleteAction::make()),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Tables\Actions\DissociateBulkAction::make(),
                    TerritoryResource::explainRefusedDeletion(DeleteBulkAction::make()),
                    RestoreBulkAction::make(),
                    TerritoryResource::explainRefusedDeletion(ForceDeleteBulkAction::make()),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }
}
