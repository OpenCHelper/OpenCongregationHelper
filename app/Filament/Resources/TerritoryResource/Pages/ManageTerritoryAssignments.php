<?php

namespace App\Filament\Resources\TerritoryResource\Pages;

use App\Filament\Resources\TerritoryAssignmentResource;
use App\Filament\Resources\TerritoryResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
        return $table
            ->heading('Storico uscite')
            // ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('assignee.full_name')
                    ->label('Assignee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_start')
                    ->label('Data di uscita')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_end')
                    ->default('(in lavorazione)')
                    ->label('Data di rientro')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignmentType.name')
                    ->label('Campagna')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                // Tables\Actions\AssociateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Tables\Actions\DissociateAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DissociateBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }
}
