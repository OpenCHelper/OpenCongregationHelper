<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

class ManageTerritoryAssignments extends ManageRelatedRecords
{
    protected static string $resource = TerritoryResource::class;

    protected static string $relationship = 'assignments';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_territory_assignments');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-pages.manage_territory_assignments_title', ['name' => $this->getRecordTitle()]);
    }

    public function getBreadcrumb(): string
    {
        return static::getNavigationLabel();
    }

    public function form(Schema $schema): Schema
    {
        return TerritoryAssignmentResource::form($schema);
    }

    // Source: https://github.com/filamentphp/filament/discussions/10168#discussioncomment-10923902
    public static function getNavigationBadge(): ?string
    {
        return TerritoryAssignmentResource::scopeToVisibleAssignments(Livewire::current()->getRecord()->assignments())->count();
    }

    public function table(Table $table): Table
    {
        return TerritoryAssignmentResource::table($table)
            // The campaign is read by the highlight of every row, even with its column hidden
            ->modifyQueryUsing(fn (Builder $query): Builder => TerritoryAssignmentResource::scopeToVisibleAssignments($query->with('campaign')))
            ->defaultSort('started_at', 'desc');
    }
}
