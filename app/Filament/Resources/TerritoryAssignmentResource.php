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
        return __('filament-resources.territory_assignment.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.territory_assignment.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-resources.territory_assignment.navigation_group');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('filament-resources.territory_assignment.section_territory_info'))
                    ->description(__('filament-resources.territory_assignment.section_territory_info_description'))
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('territory.name')
                            ->label(__('filament-resources.territory_assignment.info_territory')),
                        Infolists\Components\TextEntry::make('territory.territoryZone.name')
                            ->label(__('filament-resources.territory_assignment.info_zone')),
                        MapEntry::make('location')
                            ->label(__('filament-resources.territory_assignment.info_location'))
                            ->extraStyles(['style' => 'min-height: 50vh', 'border-radius: 8px'])
                            ->columnSpanFull()
                            ->defaultLocation(latitude: 41.902782, longitude: 12.496366)
                            // ->zoom(10)
                            // State Management
                            ->state(function ($record): array {
                                $territory = $record->territory;
                                $geojson = json_decode(strip_tags($territory->geojson ?? ''));

                                $location = [
                                    'geojson' => $geojson,
                                ];
                                if ($territory->geojson ?? null != null && $geojson !== null) {
                                    $location['lat'] = -1;
                                    $location['lng'] = -1;
                                }

                                return $location;
                            })

                            // Controls
                            // ->showFullscreenControl(true)
                            ->showZoomControl(true)
                            ->showMarker(false)
                            ->showFullscreenControl()

                            // // GeoMan Integration (if needed for viewing)
                            ->geoMan(true)
                            ->geoManEditable(false) // Usually false for infolist view
                            ->drawCircle(false)
                            ->drawPolygon(false)
                            ->drawRectangle(false)
                            ->drawMarker(false)
                            ->drawPolyline(false)
                            ->drawText(false)
                            ->editPolygon(false)
                            ->dragMode(false)
                            ->cutPolygon(false)
                            ->deleteLayer(false)
                            ->rotateMode(false)
                        ,
                        Infolists\Components\TextEntry::make('territory.geojson')
                            ->label("GeoJSON")
                            ->placeholder('NULL'),
                        Infolists\Components\ImageEntry::make('territory.image_path')
                            ->label(__('filament-resources.territory.field_image'))
                            ->visible(fn (Model $record) => $record->territory->image_path != null)
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('territory.description')
                            ->label(__('filament-resources.territory_assignment.info_description'))
                            ->columnSpanFull()
                            ->visible(fn (Model $record) => $record->territory->description != null)
                            ->placeholder(__('filament-resources.territory_assignment.info_description_placeholder')),
                        Infolists\Components\TextEntry::make('territory.notes')
                            ->label(__('filament-resources.territory_assignment.info_notes'))
                            ->icon('heroicon-o-document-text')
                            ->columnSpanFull()
                            ->visible(fn (Model $record) => $record->territory->notes != null)
                            ->placeholder(__('filament-resources.territory_assignment.info_notes_placeholder')),
                    ]),
                Infolists\Components\Section::make(__('filament-resources.territory_assignment.section_assignment_info'))
                    ->description(__('filament-resources.territory_assignment.section_assignment_info_description'))
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('assignee.full_name')
                            ->icon('heroicon-o-user')
                            ->label(__('filament-resources.territory_assignment.info_assignee')),
                        Infolists\Components\TextEntry::make('campaign.name')
                            ->icon('heroicon-o-envelope-open')
                            ->label(__('filament-resources.territory_assignment.info_campaign'))
                            ->placeholder(__('filament-resources.territory_assignment.info_campaign_placeholder')),
                        Infolists\Components\TextEntry::make('started_at')
                            ->icon('heroicon-o-calendar')
                            ->sinceTooltip()
                            ->label(__('filament-resources.territory_assignment.info_start_assignment')),
                        Infolists\Components\TextEntry::make('ended_at')
                            ->icon('heroicon-o-calendar')
                            ->sinceTooltip()
                            ->visible(fn (Model $record) => $record->ended_at != null)
                            ->label(__('filament-resources.territory_assignment.info_end_assignment'))
                            ->placeholder(__('filament-resources.territory_assignment.info_end_assignment_placeholder')),
                    ]),
            ]);
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament-resources.territory_assignment.section_base_info'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('territory_id')
                            ->label(__('filament-resources.territory_assignment.field_territory'))
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->relationship('territory', 'name')
                            ->required()
                            ->preload(),
                        Forms\Components\Select::make('assignee_id')
                            ->label(__('filament-resources.territory_assignment.field_assignee'))
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
                Forms\Components\Section::make(__('filament-resources.territory_assignment.section_main_info'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('campaign_id')
                            ->label(__('filament-resources.territory_assignment.field_campaign'))
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->relationship('campaign', 'name', function ($query) {
                                return $query->where(function ($q) {
                                    $q->whereNull('valid_until')
                                      ->orWhere('valid_until', '>', now());
                                });
                            })
                            ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign'))
                            ->rule(new \App\Rules\NotTerminatedCampaign())
                            ->preload(),
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label(__('filament-resources.territory_assignment.field_started_at'))
                            ->default(now())
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\DateTimePicker::make('ended_at')
                            ->label(__('filament-resources.territory_assignment.field_ended_at'))
                            ->columnSpan(1),
                    ]),
                Forms\Components\Section::make(__('filament-resources.territory_assignment.section_additional_info'))
                    ->description(__('filament-resources.territory_assignment.section_additional_info_description'))
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('filament-resources.territory_assignment.field_notes'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('created_by')
                            ->label(__('filament-resources.territory_assignment.field_creator'))
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
                    ->label(__('filament-resources.territory_assignment.column_territory'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignee.full_name')
                    ->label(__('filament-resources.territory_assignment.column_assignee'))
                    ->hidden(!Auth::user()->can('view_any_territory::assignment'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('filament-resources.territory_assignment.column_started_at'))
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),
                Tables\Columns\TextColumn::make('ended_at')
                    ->label(__('filament-resources.territory_assignment.column_ended_at'))
                    ->dateTime("d/m/Y H:i")
                    ->placeholder(__('filament-resources.territory_assignment.column_ended_at_placeholder'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('campaign.name')
                    ->label(__('filament-resources.territory_assignment.column_campaign'))
                    ->placeholder(__('filament-resources.territory_assignment.column_campaign_placeholder'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.full_name')
                    ->label(__('filament-resources.territory_assignment.column_creator'))
                    ->sortable(['first_name', 'last_name'])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-resources.territory_assignment.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-resources.territory_assignment.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('filament-resources.territory_assignment.column_deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('ended_at')
                    ->label(__('filament-resources.territory_assignment.filter_assignment_status'))
                    ->default(false)
                    ->nullable()
                    ->placeholder(__('filament-resources.territory_assignment.filter_assignment_status_all'))
                    ->trueLabel(__('filament-resources.territory_assignment.filter_assignment_status_returned'))
                    ->falseLabel(__('filament-resources.territory_assignment.filter_assignment_status_in_progress')),
                Tables\Filters\Filter::make('assignee')
                    ->label(__('filament-resources.territory_assignment.filter_my_assignments_only'))
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

        return $query;
    }
}
