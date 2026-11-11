<?php

namespace App\Filament\Resources\Territories;

use App\Enums\NavigationGroup;
use App\Enums\TagType;
use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\Territories\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\Territories\Pages\ViewTerritory;
use App\Filament\Resources\TerritoryAssignments\Actions as AssignmentActions;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Carbon\Carbon;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Dotswan\MapPicker\Fields\Map;
use Exception;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Override;
use Spatie\Tags\Tag;
use UnitEnum;

class TerritoryResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Territory::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::MapPin;

    protected static ?int $navigationSort = 0;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('filament-resources.territory.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.territory.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Territories;
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
            ...(! $tags->isEmpty() ? [__('filament-resources.global_search_results.tags') => $tags->map(fn ($tag) => $tag->name)->join(',')] : []),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name.' ('.$record->territoryZone->name.')';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // GazeBanner::make()
                //     ->lock()
                //     ->columnSpanFull()
                //     // ->canTakeControl(fn() => auth()->user()->isAdmin())
                //     ->hiddenOn(['create', 'view']),
                Section::make(__('filament-resources.territory.section_fundamental_info'))
                    ->description(__('filament-resources.territory.section_fundamental_info_description'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-resources.territory.field_name'))
                            ->required(),
                        Select::make('territory_zone_id')
                            ->label(__('filament-resources.territory.field_territory_zone'))
                            ->required()
                            ->relationship(name: 'territoryZone', titleAttribute: 'name')
                            ->preload()
                            ->searchable()
                            ->createOptionForm(fn (Schema $schema) => TerritoryZoneResource::form($schema)),
                        Toggle::make('is_commercial')
                            ->label(__('filament-resources.territory.field_is_commercial'))
                            // ->inline(false)
                            ->onIcon(Heroicon::OutlinedBuildingStorefront)
                            ->offIcon(Heroicon::OutlinedHome),
                        Tabs::make('Tabs')
                            ->columnSpanFull()
                            // ->contained(false)
                            ->hidden(fn (?Territory $record) => $schema->getOperation() === 'view' && is_null($record?->geojson) && is_null($record?->image_path))
                            ->tabs([
                                Tab::make('TabMap')
                                    ->label(__('filament-resources.territory.tab_map'))
                                    ->icon(Heroicon::OutlinedMap)
                                    ->hidden(fn (?Territory $record) => $schema->getOperation() === 'view' && $record?->geojson == null)
                                    ->schema([
                                        Map::make('location')
                                            ->label(__('filament-resources.territory.field_location'))
                                            ->columnSpanFull()
                                            ->extraStyles(['style' => 'min-height: 50vh', 'border-radius: 8px'])
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?array $state): void {
                                                // The map picker also sends its centre alone (`lat` and `lng`) when it
                                                // loads or moves: keep the drawn shapes instead of erasing them.
                                                if (! is_array($state) || ! array_key_exists('geojson', $state)) {
                                                    $set('location', [
                                                        ...($state ?? []),
                                                        'geojson' => json_decode((string) $get('geojson')),
                                                    ]);

                                                    return;
                                                }

                                                $set('geojson', json_encode($state['geojson']));
                                            })
                                            ->afterStateHydrated(function ($record, Set $set): void {
                                                $geojson = json_decode(strip_tags($record->geojson ?? ''));
                                                $location = [
                                                    'geojson' => $geojson,
                                                ];
                                                if ($record?->geojson !== null && $geojson !== null) {
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
                                            ->geoManEditable($schema->getOperation() !== 'view')
                                            ->geoManPosition('topleft')
                                            ->rotateMode(false)
                                            ->clickable(false) // click to move marker
                                            ->drawMarker(false)
                                            ->drawPolygon($schema->getOperation() !== 'view')
                                            ->drawPolyline($schema->getOperation() !== 'view')
                                            ->drawCircle(false)
                                            ->drawCircleMarker(false)
                                            ->dragMode(false)
                                            ->drawText($schema->getOperation() !== 'view')
                                            ->drawRectangle($schema->getOperation() !== 'view')
                                            ->cutPolygon($schema->getOperation() !== 'view')
                                            ->editPolygon($schema->getOperation() !== 'view')
                                            ->deleteLayer($schema->getOperation() !== 'view')
                                            ->setColor('#3388ff')
                                            ->setFilledColor('#cad9ec'),
                                        TextInput::make('geojson')
                                            ->readOnly(),
                                    ]),
                                Tab::make('TabImage')
                                    ->label(__('filament-resources.territory.tab_image'))
                                    ->hidden(fn (?Territory $record) => $schema->getOperation() === 'view' && $record?->image_path == null)
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->schema([
                                        TextEntry::make('image_preview')
                                            ->hiddenLabel()
                                            ->columnSpanFull()
                                            ->extraAttributes([
                                                'id' => 'territory-image-preview',
                                                'style' => 'min-height: 150px;',
                                            ])
                                            ->default(new HtmlString(
                                                '<div class="no-image-set">'.__('filament-resources.territory.missing_image').'</div>'
                                            ))
                                            ->state(function (Get $get) {
                                                $file = $get('image_file');
                                                $url = $get('image_path');

                                                if ($file instanceof TemporaryUploadedFile) {
                                                    return new HtmlString(
                                                        '<img src="'.e($file->temporaryUrl()).'" class="rounded-lg">'
                                                    );
                                                }

                                                if (! filled($url)) {
                                                    return null;
                                                }

                                                // Local path
                                                if (! Str::of($url)->isMatch('/^https?:\/\//')) {
                                                    $url = Storage::disk('local')->temporaryUrl(
                                                        $url,
                                                        now()->plus(minutes: 5)
                                                    );
                                                }

                                                return new HtmlString(
                                                    '<img src="'.e($url).'" class="rounded-lg">'
                                                );
                                            })
                                            ->live(),

                                        TextInput::make('image_path')
                                            ->label('Image from URL')
                                            ->url()
                                            ->autocomplete(false)
                                            ->live()
                                            ->copyable()
                                            ->hiddenOn(['view'])
                                            ->disabled(fn ($get) => filled($get('image_file')))
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (filled($state)) {
                                                    $set('image_file', null);
                                                }
                                            }),
                                        TextEntry::make('image_separator')
                                            ->hiddenLabel()
                                            ->state('— or —')
                                            ->hiddenOn(['view'])
                                            ->extraAttributes([
                                                'class' => 'flex items-center justify-center h-full',
                                            ]),
                                        FileUpload::make('image_file')
                                            ->label('Image from file')
                                            ->image()
                                            ->panelLayout('integrated')
                                            ->imageEditor()
                                            ->openable()
                                            ->downloadable()
                                            ->previewable(false)
                                            ->moveFiles()
                                            ->disk('local')
                                            ->visibility('private')
                                            ->directory('territories')
                                            ->live()
                                            ->hiddenOn(['view'])
                                            ->dehydrated()
                                            ->disabled(fn ($get) => filled($get('image_path')))
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (filled($state) && ! $state instanceof TemporaryUploadedFile) {
                                                    $set('image_path', $state);
                                                }
                                                if ($state == null) {
                                                    $set('image_path', null);
                                                }
                                            })
                                            ->partiallyRenderComponentsAfterStateUpdated([
                                                'image_path',
                                                'image_preview',
                                            ]),
                                    ]),
                            ])
                            ->activeTab(function (?Model $record) {
                                return $record?->geojson ? 2 : 1;
                            }),
                    ]),
                Section::make(__('filament-resources.territory.section_additional_info'))
                    ->description(__('filament-resources.territory.section_additional_info_description'))
                    ->columns(1)
                    ->schema([
                        // Forms\Components\TextInput::make('dimensione')
                        //     ->numeric(),
                        Textarea::make('notes')
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
                    ->getTitleFromRecordUsing(function (Territory $record): string {
                        // Count the territories of every zone with a single query per request
                        $territoriesCountByZone = once(fn () => Territory::query()
                            ->selectRaw('territory_zone_id, count(*) as aggregate')
                            ->groupBy('territory_zone_id')
                            ->pluck('aggregate', 'territory_zone_id'));

                        return $record->territoryZone->name.' ('.($territoriesCountByZone[$record->territory_zone_id] ?? 0).')';
                    })
                    ->getDescriptionFromRecordUsing(fn (Territory $record): string => $record->territoryZone->description ?? '')
                    ->collapsible(),
                Group::make('currentAssignment.campaign.name')
                    ->label(ucfirst(__('filament-resources.campaign.model_label')))
                    ->getTitleFromRecordUsing(function (Territory $record): string {
                        /** @var Campaign $campaign */
                        $campaign = $record->currentAssignment?->campaign;
                        if ($campaign) {
                            // Count the assignments of every campaign with a single query per request
                            $assignmentsCountByCampaign = once(fn () => TerritoryAssignment::query()
                                ->whereNotNull('campaign_id')
                                ->selectRaw('campaign_id, count(*) as aggregate')
                                ->groupBy('campaign_id')
                                ->pluck('aggregate', 'campaign_id'));

                            return $campaign->name.' ('.($assignmentsCountByCampaign[$campaign->id] ?? 0).')';
                        }

                        $territoriesCount = once(fn (): int => Territory::unassigned()->count());

                        return __('filament-resources.territory.filter_no_campaign')." ({$territoriesCount})";
                    })
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        return $query->leftJoin('territory_assignments as campaign_assignments', function ($join) {
                            $join->on('territories.id', '=', 'campaign_assignments.territory_id')
                                ->whereNull('campaign_assignments.ended_at');
                        })
                            ->leftJoin('campaigns', 'campaign_assignments.campaign_id', '=', 'campaigns.id')
                            ->orderByRaw("COALESCE(campaigns.name, 'zzz_no_campaign') {$direction}")
                            ->select('territories.*');
                    })
                    ->collapsible(),
            ])
            ->striped()
            ->recordUrl(fn (Model $record): string => TerritoryResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.territory.column_name'))
                    ->sortable()
                    ->searchable(isIndividual: true),
                TextColumn::make('territoryZone.name')
                    ->label(__('filament-resources.territory.column_zone'))
                    ->sortable()
                    ->searchable(isIndividual: true),
                IconColumn::make('is_commercial')
                    ->label(__('filament-resources.territory.column_commercial'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currentAssignment.assignee.full_name')
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
                TextColumn::make('assignmentDuration')
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

                        // Se non c'è assegnazione attiva, usa l'ultima assegnazione terminata (caricata in anticipo)
                        $lastAssignment = $record->lastReturnedAssignment;

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
                            // Portable equivalent of sorting by `DATEDIFF(NOW(), started_at)`: unassigned
                            // territories count as the shortest duration, and the earlier an open assignment
                            // started, the longer its duration.
                            ->orderByRaw('CASE WHEN duration_assignments.started_at IS NULL THEN 0 ELSE 1 END '.($direction === 'desc' ? 'desc' : 'asc'))
                            ->orderBy('duration_assignments.started_at', $direction === 'desc' ? 'asc' : 'desc')
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
                TextColumn::make('created_at')
                    ->label(__('filament-resources.territory.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-resources.territory.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('geojson')
                    ->nullable()
                    ->label(__('filament-resources.territory.filter_map'))
                    ->placeholder(__('filament-resources.territory.filter_map_any'))
                    ->trueLabel(__('filament-resources.territory.filter_map_available'))
                    ->falseLabel(__('filament-resources.territory.filter_map_unavailable')),
                TernaryFilter::make('assignment_status')
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
                SelectFilter::make('tags')
                    ->label(__('filament-resources.territory.filter_tags'))
                    ->multiple()
                    ->options(Tag::getWithType(TagType::TERRITORIES->value)->pluck('name', 'name'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['values'], function (Builder $query, $data): Builder {
                            return $query->withAnyTags(array_values($data), TagType::TERRITORIES->value);
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    AssignmentActions\CreateAssignmentAction::make('create_assignment'),
                    AssignmentActions\ReturnAssignmentAction::make('cancel_assignment'),
                    ForceDeleteAction::make()
                        ->visible(fn (Territory $record) => $record->deleted_at !== null),
                    RestoreAction::make()
                        ->visible(fn (Territory $record) => $record->deleted_at !== null),

                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('create_assignment_bulk')
                        ->label(__('filament-resources.territory.action_assign_massively'))
                        ->icon(Heroicon::OutlinedDocumentArrowUp)
                        ->authorize(fn (): bool => Gate::allows('create', TerritoryAssignment::class))
                        ->schema([
                            Select::make('assignee_id')
                                ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                                ->options(fn (): array => User::getAssigneeOptions())
                                ->searchable()
                                ->required(),
                            Select::make('campaign_id')
                                ->label(__('filament-resources.territory_assignment.field_campaign'))
                                ->options(fn (): array => Campaign::query()->notTerminated()->pluck('name', 'id')->all())
                                ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign')),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            // Check if campaign is terminated
                            if ($data['campaign_id'] && Campaign::find($data['campaign_id'])?->isTerminated()) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('filament-resources.database_notifications.assignment_terminated_campaign_title'))
                                    ->body(__('filament-resources.database_notifications.assignment_terminated_campaign_body'))
                                    ->send();

                                return;
                            }

                            foreach ($records as $record) {
                                // Skip if the last assignment is not null
                                if ($record->currentAssignment !== null && $record->currentAssignment->ended_at === null) {
                                    Log::warning("Skipping record {$record->id} because it has an active assignment");

                                    continue;
                                }

                                try {
                                    // The transaction makes the "already assigned" check (see TerritoryAssignmentObserver) atomic
                                    DB::transaction(fn () => $record->assignments()->create([
                                        'assignee_id' => $data['assignee_id'],
                                        'territory_id' => $record->id,
                                        'campaign_id' => $data['campaign_id'] ?? null,
                                        'created_by' => Auth::id(),
                                    ]));
                                } catch (ValidationException) {
                                    Log::warning("Skipping record {$record->id} because it has been assigned in the meantime");
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('cancel_assignment_bulk')
                        ->label(__('filament-resources.territory.action_end_assignments'))
                        ->icon(Heroicon::OutlinedDocumentArrowDown)
                        ->authorize(fn (): bool => Gate::allows('update', new TerritoryAssignment))
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                // Skip if the last assignment is not null
                                if ($record->currentAssignment === null || $record->currentAssignment->ended_at !== null) {
                                    Log::warning("Skipping record {$record->id} because it does not has an active assignment");

                                    continue;
                                }

                                $currentAssignment = $record->currentAssignment;

                                if (Gate::denies('update', $currentAssignment)) {
                                    Log::warning("Skipping record {$record->id} because the user cannot update its assignment");

                                    continue;
                                }

                                // Carica la relazione territory prima dell'update per evitare lazy loading errors
                                $currentAssignment->load('territory');
                                $currentAssignment->update([
                                    'ended_at' => now(),
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                    ForceDeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                    RestoreBulkAction::make()
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
            'index' => ListTerritories::route('/'),
            'create' => CreateTerritory::route('/create'),
            'edit' => EditTerritory::route('/{record}/edit'),
            'view' => ViewTerritory::route('/{record}'),
            'assignments' => ManageTerritoryAssignments::route('/{record}/assignments'),
        ];
    }

    public static function getDocumentation(): array
    {
        return [
            'territories.introduction',
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewTerritory::class,
            ManageTerritoryAssignments::class,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['currentAssignment.assignee', 'lastReturnedAssignment'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Store the uploaded image file (`image_file`, a form-only field) as the territory image (`image_path`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws Exception when both an image URL and an uploaded file are given
     */
    public static function mergeUploadedImageIntoFormData(array $data): array
    {
        if (isset($data['image_path'], $data['image_file'])) {
            throw new Exception("Form 'image_path' and 'image_file' cannot be set at the same time");
        }

        if (isset($data['image_file'])) {
            $data['image_path'] = $data['image_file'];
        }

        unset($data['image_file']);

        return $data;
    }
}
