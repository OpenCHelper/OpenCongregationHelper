<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryAssignmentResource\Pages;
use App\Filament\Resources\TerritoryAssignmentResource\RelationManagers;
use App\Models\TerritoryAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TerritoryAssignmentResource extends Resource
{
    protected static ?string $model = TerritoryAssignment::class;

    protected static ?string $navigationGroup = 'Gestione assegnazioni';
    protected static ?string $navigationLabel = "Assignments";
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni base')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('territory_id')
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->relationship('territory', 'name')
                            ->required()
                            ->preload(),
                        Forms\Components\Select::make('assignee_id')
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->required()
                            ->relationship(name: 'assignee', titleAttribute: 'name')
                            ->preload(),
                    ]),
                Forms\Components\Section::make('Informazioni principali')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('territory_assignment_type_id')
                            ->required()
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->relationship('assignmentType', 'name')
                            ->preload(),
                        Forms\Components\DateTimePicker::make('date_start')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\DateTimePicker::make('date_end')
                            ->columnSpan(1),
                    ]),
                Forms\Components\Section::make('Informazioni aggiuntive')
                    ->description('Eventuali note o informazioni aggiuntive utili alla lavorazione.')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Eventuali note')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('created_by')
                            ->default(Auth::id())
                            ->disabled()
                            ->hiddenOn('create')
                            ->relationship(name: 'creator', titleAttribute: 'name')
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('territory.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_start')
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_end')
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignmentType.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('date_end')
                    ->label('Stato assegnazione')
                    ->nullable()
                    ->placeholder('Tutti gli stati')
                    ->trueLabel('Restituito')
                    ->falseLabel('In lavorazione'),
            ])
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTerritoryAssignments::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
