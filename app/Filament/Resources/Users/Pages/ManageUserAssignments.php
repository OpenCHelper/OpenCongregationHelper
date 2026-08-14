<?php

namespace App\Filament\Resources\Users\Pages;

use Filament\Schemas\Schema;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

class ManageUserAssignments extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'assignments';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_user_assignments');
    }

    // Source: https://github.com/filamentphp/filament/discussions/10168#discussioncomment-10923902
    public static function getNavigationBadge(): ?string
    {
        return Livewire::current()->getRecord()->assignments->count();
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
            ->defaultSort('started_at', 'desc');
    }
}
