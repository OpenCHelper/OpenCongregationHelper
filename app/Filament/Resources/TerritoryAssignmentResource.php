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

use Filament\Infolists;
use Filament\Infolists\Infolist;
use Dotswan\MapPicker\Infolists\MapEntry;

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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informazioni territorio')
                    ->description('Informazioni principali relative all\'assegnazione del territorio.')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('territory.name')
                            ->label('Territorio'),
                        Infolists\Components\TextEntry::make('territory.territoryZone.name')
                            ->label('Zona'),
                        // MapEntry::make('location')
                        //     ->label('Mappa')
                        //     ->columnSpanFull()

                        //     // State Management
                        //     ->state(function ($record): array {
                        //         $territory = $record->territory;
                        //         $geojson = json_decode(strip_tags($territory->geojson ?? ''));
                        //         $location = [
                        //             'geojson' => $geojson,
                        //         ];
                        //         if ($territory->geojson ?? null != null && $geojson !== null) {
                        //             $location['lat'] = -1;
                        //             $location['lng'] = -1;
                        //         }

                        //         return $location;
                        //     })
                            
                        //     // Controls
                        //     ->showFullscreenControl(true)
                        //     ->showZoomControl(true)

                        //     // GeoMan Integration (if needed for viewing)
                        //     ->geoMan(true)
                        //     ->geoManEditable(false),
                        Infolists\Components\ImageEntry::make('territory.image_path')
                            ->label('Immagine')
                            ->visible(fn (Model $record) => $record->territory->image_path != null)
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('territory.description')
                            ->label('Descrizione')
                            ->columnSpanFull()
                            ->visible(fn (Model $record) => $record->territory->description != null)
                            ->placeholder('Nessuna descrizione disponibile'),
                        Infolists\Components\TextEntry::make('territory.notes')
                            ->label('Note')
                            ->icon('heroicon-o-document-text')
                            ->columnSpanFull()
                            ->visible(fn (Model $record) => $record->territory->notes != null)
                            ->placeholder('Nessuna nota disponibile'),
                    ]),
                Infolists\Components\Section::make('Informazioni assegnazione')
                    ->description('Informazioni principali relative all\'assegnazione del territorio.')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('assignee.full_name')
                            ->icon('heroicon-o-user')
                            ->label('Assegnato a'),
                        Infolists\Components\TextEntry::make('assignmentType.name')
                            ->icon('heroicon-o-envelope-open')
                            ->label('Tipo di assegnazione'),
                        Infolists\Components\TextEntry::make('started_at')
                            ->icon('heroicon-o-calendar')
                            ->sinceTooltip()
                            ->label('Inizio assegnazione'),
                        Infolists\Components\TextEntry::make('ended_at')
                            ->icon('heroicon-o-calendar')
                            ->sinceTooltip()
                            ->visible(fn (Model $record) => $record->ended_at != null)
                            ->label('Fine assegnazione')
                            ->placeholder('(in lavorazione)'),
                    ]),
            ]);
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
                        Forms\Components\DateTimePicker::make('started_at')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\DateTimePicker::make('ended_at')
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
            ->recordUrl(fn (Model $record): string => TerritoryAssignmentResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('territory.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignee.full_name')
                    ->hidden(!Auth::user()->can('view_any_territory::assignment'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),
                Tables\Columns\TextColumn::make('ended_at')
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
                Tables\Filters\TernaryFilter::make('ended_at')
                    ->label('Stato assegnazione')
                    ->default(false)
                    ->nullable()
                    ->placeholder('Tutti gli stati')
                    ->trueLabel('Restituito')
                    ->falseLabel('In lavorazione'),
                Tables\Filters\Filter::make('assignee')
                    ->label('Solo le mie assegnazioni')
                    ->query(fn (Builder $query) => $query->where('assignee_id', Auth::user()->id))
                    ->hidden(!Auth::user()->can('view_any_territory::assignment')),
            ])
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
            'view' => Pages\ViewTerritoryAssignment::route('/{record}'),
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
