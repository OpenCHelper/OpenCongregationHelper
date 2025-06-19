<?php

namespace App\Filament\Resources;

use App\Enums\TagType;
use App\Filament\Actions\Assignment as AssignmentActions;
use App\Filament\Resources\TerritoryResource\Pages;
use App\Models\Territory;
use App\Models\Campaign;
use App\Models\User;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\SpatieTagsInput;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Set;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Grouping\Group;
use Spatie\Tags\Tag;
use Illuminate\Contracts\Support\Htmlable;
use Carbon\Carbon;

class TerritoryResource extends Resource
{
    protected static ?string $model = Territory::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 0;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;


    public static function getModelLabel(): string
    {
        return __('filament-resources.territory.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.territory.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-resources.territory.navigation_group');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['territoryZone', 'tags']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'territoryZone.name', 'notes', 'tags.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $tags = $record->tagsWithType(TagType::TERRITORIES->value);

        return [
            ...($record->description ? [__('filament-resources.global_search_results.description') => $record->description] : []),
            ...(!$tags->isEmpty() ? [__('filament-resources.global_search_results.tags') => $tags->map(fn ($tag) => $tag->name)->join(',')] : []),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->name . ' (' . $record->territoryZone->name . ')';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // GazeBanner::make()
                //     ->lock()
                //     ->columnSpanFull()
                //     // ->canTakeControl(fn() => auth()->user()->isAdmin())
                //     ->hiddenOn(['create', 'view']),
                Forms\Components\Section::make(__('filament-resources.territory.section_fundamental_info'))
                    ->description(__('filament-resources.territory.section_fundamental_info_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('filament-resources.territory.field_name'))
                            ->required(),
                        Forms\Components\Select::make('territory_zone_id')
                            ->label(__('filament-resources.territory.field_territory_zone'))
                            ->required()
                            ->relationship(name: 'territoryZone', titleAttribute: 'name')
                            ->preload()
                            ->searchable()
                            ->createOptionForm(fn(Form $form) => TerritoryZoneResource::form($form)),
                        Forms\Components\Toggle::make('is_commercial')
                            ->label(__('filament-resources.territory.field_is_commercial'))
                            // ->inline(false)
                            ->onIcon('heroicon-o-building-storefront')
                            ->offIcon('heroicon-o-home'),
                        Forms\Components\Tabs::make('Tabs')
                            ->columnSpanFull()
                            ->contained(false)
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('TabImage')
                                    ->label(__('filament-resources.territory.tab_image'))
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->extraAttributes(['style' => 'min-height: 150px;'])
                                            ->image()
                                            ->panelLayout('integrated')
                                            ->imageEditor()
                                            ->openable()
                                            ->downloadable()
                                            ->previewable()
                                            ->moveFiles()
                                            ->disk('local')
                                            ->visibility('private')
                                            ->directory('territories'),
                                    ]),
                                Forms\Components\Tabs\Tab::make('TabMap')
                                    ->label(__('filament-resources.territory.tab_map'))
                                    ->icon('heroicon-o-map')
                                    ->schema([
                                        Map::make('location')
                                            ->label(__('filament-resources.territory.field_location'))
                                            ->columnSpanFull()
                                            ->extraStyles(['style' => 'min-height: 50vh', 'border-radius: 8px'])
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function (Set $set, ?array $state): void {
                                                $set('geojson',   json_encode($state['geojson'] ?? ''));
                                            })
                                            ->afterStateHydrated(function ($state, $record, Set $set): void {
                                                $geojson = json_decode(strip_tags($record->geojson ?? ''));
                                                $location = [
                                                    'geojson' => $geojson,
                                                ];
                                                if ($record->geojson ?? null != null && $geojson !== null) {
                                                    $location['lat'] = -1;
                                                    $location['lng'] = -1;
                                                }

                                                $set('location', $location);
                                            })
                                            ->showMarker(false)
                                            ->showFullscreenControl()
                                            ->showZoomControl()
                                            ->draggable()
                                            ->zoom(15)
                                            ->showMyLocationButton()
                                            ->geoMan()
                                            ->geoManEditable($form->getOperation() !== 'view')
                                            ->geoManPosition('topleft')
                                            ->rotateMode(false)
                                            ->clickable(false) //click to move marker
                                            ->drawMarker(false)
                                            ->drawPolygon($form->getOperation() !== 'view')
                                            ->drawPolyline(true)
                                            ->drawCircle(false)
                                            ->drawCircleMarker(false)
                                            ->dragMode(false)
                                            ->cutPolygon(false)
                                            ->editPolygon($form->getOperation() !== 'view')
                                            ->deleteLayer($form->getOperation() !== 'view')
                                            ->setColor('#3388ff')
                                            ->setFilledColor('#cad9ec'),
                                        Forms\Components\TextInput::make('geojson')
                                            // ->hiddenLabel()
                                            ->readOnly(),
                                ]),
                            ])
                            ->activeTab(function (?Model $record) {
                                return $record?->geojson ? 2 : 1;
                            })
                    ]),
                Forms\Components\Section::make(__('filament-resources.territory.section_additional_info'))
                    ->description(__('filament-resources.territory.section_additional_info_description'))
                    ->columns(1)
                    ->schema([
                        // Forms\Components\TextInput::make('dimensione')
                        //     ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->label(__('filament-resources.territory.field_notes'))
                            ->helperText(__('filament-resources.territory.field_notes_helper'))
                            ->columnSpanFull(),
                        SpatieTagsInput::make('tags')
                            ->label(__('filament-resources.territory.field_tags'))
                            ->type(TagType::TERRITORIES->value),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('territoryZone.name')
                    ->label(ucfirst(__('filament-resources.territory_zone.model_label')))
                    ->getTitleFromRecordUsing(fn (Territory $record): string => $record->territoryZone->name . " (" . $record->territoryZone->loadMissing("territories")->territories->count() . ")" ?? '')
                    ->getDescriptionFromRecordUsing(fn (Territory $record): string => $record->territoryZone->description ?? '')
                    ->collapsible(),
            ])
            ->striped()
            ->recordUrl(fn (Model $record): string => TerritoryResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-resources.territory.column_name'))
                    ->sortable()
                    ->searchable(isIndividual: true),
                Tables\Columns\TextColumn::make('territoryZone.name')
                    ->label(__('filament-resources.territory.column_zone'))
                    ->sortable()
                    ->searchable(isIndividual: true),
                Tables\Columns\IconColumn::make('is_commercial')
                    ->label(__('filament-resources.territory.column_commercial'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('currentAssignment.assignee.full_name')
                    ->label(__('filament-resources.territory.column_current_assignment'))
                    ->default('')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('territory_assignments as current_assignments', function ($join) {
                                $join->on('territories.id', '=', 'current_assignments.territory_id')
                                     ->whereNull('current_assignments.ended_at');
                            })
                            ->leftJoin('users as current_assignees', 'current_assignments.assignee_id', '=', 'current_assignees.id')
                            ->orderBy('current_assignees.first_name', $direction)
                            ->orderBy('current_assignees.last_name', $direction)
                            ->select('territories.*');
                    })
                    ->searchable(false),
                Tables\Columns\TextColumn::make('assignmentDuration')
                    ->state(function (Territory $record): ?string {
                        $currentAssignment = $record->currentAssignment;
                        
                        // Se c'è un'assegnazione attiva
                        if ($currentAssignment && $currentAssignment->ended_at === null) {
                            $startDate = Carbon::parse($currentAssignment->started_at);
                            $now = Carbon::now();
                            $diffInDays = round($startDate->diffInDays($now));
                            
                            if ($diffInDays == 0) {
                                return __('filament-resources.territory.assignment_duration_today');
                            } else {
                                return trans_choice('filament-resources.territory.assignment_duration_day', $diffInDays, ['count' => $diffInDays]);
                            }
                        }
                        
                        // Se non c'è assegnazione attiva, cerca l'ultima assegnazione terminata
                        $lastAssignment = $record->assignments()
                            ->whereNotNull('ended_at')
                            ->orderBy('ended_at', 'desc')
                            ->first();
                            
                        if ($lastAssignment) {
                            $endDate = Carbon::parse($lastAssignment->ended_at);
                            $now = Carbon::now();
                            $diffInDays = round($endDate->diffInDays($now));
                            
                            if ($diffInDays == 0) {
                                return __('filament-resources.territory.assignment_ended_today');
                            } else {
                                return trans_choice('filament-resources.territory.assignment_ended_day_ago', $diffInDays, ['count' => $diffInDays]);
                            }
                        }
                        
                        return null;
                    })
                    ->label(__('filament-resources.territory.column_assignment_duration'))
                    ->placeholder(__('filament-resources.territory.assignment_duration_not_assigned'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('territory_assignments as duration_assignments', function ($join) {
                                $join->on('territories.id', '=', 'duration_assignments.territory_id')
                                     ->whereNull('duration_assignments.ended_at');
                            })
                            ->orderByRaw("
                                CASE 
                                    WHEN duration_assignments.started_at IS NOT NULL 
                                    THEN DATEDIFF(NOW(), duration_assignments.started_at)
                                    ELSE NULL
                                END {$direction}
                            ")
                            ->select('territories.*');
                    })
                    ->searchable(false),
                SpatieTagsColumn::make('tags')
                    ->label(__('filament-resources.territory.column_tags'))
                    // ->searchable(isIndividual: true, query: function (Builder $query, string $search): Builder {
                    //     return $query->when($search, function (Builder $query, $search): Builder {
                    //         return $query->withAnyTags($search, TagType::TERRITORIES->value);
                    //     });
                    // })
                    ->type(TagType::TERRITORIES->value),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-resources.territory.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-resources.territory.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('assignment_status')
                    ->nullable()
                    ->label(__('filament-resources.territory.filter_assignment_status'))
                    ->placeholder(__('filament-resources.territory.status_all'))
                    ->trueLabel(__('filament-resources.territory.status_assigned'))
                    ->falseLabel(__('filament-resources.territory.status_unassigned'))
                    ->queries(
                        true: fn (Builder $query) => $query->assigned(),
                        false: fn (Builder $query) => $query->unassigned(),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('tags')
                    ->label(__('filament-resources.territory.filter_tags'))
                    ->multiple()
                    ->options(Tag::getWithType(TagType::TERRITORIES->value)->pluck('name', 'name'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['values'], function (Builder $query, $data): Builder {
                            return $query->withAnyTags(array_values($data), TagType::TERRITORIES->value);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    AssignmentActions\CreateAssignmentActionTable::make('create_assignment'),
                    AssignmentActions\DeleteAssignmentActionTable::make('cancel_assignment'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('create_assignment_bulk')
                        ->label(__('filament-resources.territory.action_assign_massively'))
                        ->icon('heroicon-o-document-arrow-up')
                        ->form([
                            Forms\Components\Select::make('assignee_id')
                                ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                                ->options(User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                                ->searchable()
                                ->required(),
                            Forms\Components\Select::make('assignment_type')
                                ->label(__('filament-resources.territory_assignment.field_assignment_type'))
                                ->options(Campaign::query()->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                // Skip if the last assignment is not null
                                if ($record->currentAssignment !== null && $record->currentAssignment->ended_at === null) {
                                    Log::warning("Skipping record {$record->id} because it has an active assignment");
                                    continue;
                                }

                                $record->assignments()->create([
                                    'assignee_id' => $data['assignee_id'],
                                    'territory_id' => $record->id,
                                    'campaign_id' => $data['assignment_type'],
                                    'created_by' => Auth::id(),
                                ]);
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    // AssignmentActions\BulkCreateAssignmentActionTable::make('create_assignment_bulk')
                    //     ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('cancel_assignment_bulk')
                        ->label(__('filament-resources.territory.action_end_assignments'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                // Skip if the last assignment is not null
                                if ($record->currentAssignment === null || $record->currentAssignment->ended_at !== null) {
                                    Log::warning("Skipping record {$record->id} because it does not has an active assignment");
                                    continue;
                                }

                                $currentAssignment = $record->currentAssignment;
                                // Carica la relazione territory prima dell'update per evitare lazy loading errors
                                $currentAssignment->load('territory');
                                $currentAssignment->update([
                                    'ended_at' => now(),
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerritories::route('/'),
            'create' => Pages\CreateTerritory::route('/create'),
            'edit' => Pages\EditTerritory::route('/{record}/edit'),
            'view' => Pages\ViewTerritory::route('/{record}'),
            'assignments' => Pages\ManageTerritoryAssignments::route('/{record}/assignments'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewTerritory::class,
            Pages\ManageTerritoryAssignments::class,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['currentAssignment.assignee'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
