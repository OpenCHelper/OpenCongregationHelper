<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

class ManageUserAssignments extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'assignments';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_user_assignments');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-pages.manage_user_assignments_title', ['name' => $this->getRecordTitle()]);
    }

    public function getBreadcrumb(): string
    {
        return static::getNavigationLabel();
    }

    // Source: https://github.com/filamentphp/filament/discussions/10168#discussioncomment-10923902
    public static function getNavigationBadge(): ?string
    {
        return TerritoryAssignmentResource::scopeToVisibleAssignments(Livewire::current()->getRecord()->assignments())->count();
    }

    public function form(Schema $schema): Schema
    {
        return TerritoryAssignmentResource::form($schema);
    }

    public function table(Table $table): Table
    {
        $inherited_table = TerritoryAssignmentResource::table($table);

        // Remove the "assignee.full_name" column from the inherited table,
        // since it would always be the same as the current user.
        $inherited_table->getColumn('assignee.full_name')->hidden();

        return $inherited_table
            // The campaign is read by the highlight of every row, even with its column hidden
            ->modifyQueryUsing(fn (Builder $query): Builder => TerritoryAssignmentResource::scopeToVisibleAssignments($query->with('campaign')))
            ->defaultSort('started_at', 'desc');
    }
}
