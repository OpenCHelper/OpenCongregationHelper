<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryAssignmentResource\Pages;
use App\Filament\Resources\TerritoryAssignmentResource\RelationManagers;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TerritoryAssignmentResource extends Resource
{
    protected static ?string $model = TerritoryAssignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Hide the resource from the navigation panel
    protected static bool $shouldRegisterNavigation = true;
    
    public static function getModelLabel(): string
    {
        return 'assegnazione';
    }

    public static function getPluralModelLabel(): string
    {
        return 'assegnazioni';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reparto territori';
    }

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
                            ->searchable(['first_name', 'last_name'])
                            ->disabledOn('edit', 'view')
                            ->required()
                            ->relationship(
                                name: 'assignee',
                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->full_name)
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
                            ->relationship(
                                name: 'creator',
                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->full_name)
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
                Tables\Columns\TextColumn::make('assignee.full_name')
                    ->hidden(!Auth::user()->can('view_any_territory::assignment'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('date_start')
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_end')
                    ->dateTime("d/m/Y H:i")
                    ->placeholder('(in lavorazione)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignmentType.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.full_name')
                    ->sortable(['first_name', 'last_name'])
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
        $query = parent::getEloquentQuery()
            ->whereHas('assignee', function ($query) {
                // If the user cannot see all assignments, filter by the user's ID
                if (!Auth::user()->can('view_any_territory::assignment')) {
                    return $query->where('id', Auth::user()->id);
                }
            })
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Log the generated SQL query for debugging
        Log::info('Generated SQL query:', [
            'query' => $query->toSql(),
        ]);
        return $query;
    }

    public static function canAccess(): bool
    {
        // Allow access to the resource only if the user has the 'view_any_territory::assignment' permission
        return Auth::user()->can('view_territory::assignment') || parent::canAccess();
    }
}
