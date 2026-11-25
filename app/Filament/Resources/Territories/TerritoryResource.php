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
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use App\Models\Campaign;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use App\Observers\TerritoryObserver;
use App\Support\MapCenter;
use Carbon\Carbon;
use Closure;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Dotswan\MapPicker\Fields\Map;
use Exception;
use Filament\Actions\Action;
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
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Override;
use UnitEnum;
use WeakMap;

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

    /**
     * Counts of the territories of each table group, by Livewire component and count query.
     *
     * @var WeakMap<HasTable, array<string, array<array-key, int>>>|null
     */
    protected static ?WeakMap $tableTerritoriesCountsByGroup = null;

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

    /**
     * Get the zone whose territories the map of the form opens on, if known when the form is built:
     * the zone of the territory being edited, or the zone whose territories page the form is on.
     */
    protected static function getMapCenterZoneId(Schema $schema): ?int
    {
        $livewire = $schema->getLivewire();

        if ($livewire instanceof ManageZoneTerritories) {
            return (int) $livewire->getOwnerRecord()->getKey();
        }

        $record = $schema->getRecord();

        return $record instanceof Territory && $record->territory_zone_id !== null ? (int) $record->territory_zone_id : null;
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
                            ->required()
                            // Deleted territories count too, like in the unique index of the database
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get, string $operation, HasSchemas $livewire): Unique => $rule->where(
                                    'territory_zone_id',
                                    // On the territories page of a zone, new territories always go in that zone
                                    ($operation === 'create') && ($livewire instanceof ManageZoneTerritories)
                                        ? $livewire->getOwnerRecord()->getKey()
                                        : $get('territory_zone_id'),
                                ),
                            )
                            ->validationMessages([
                                'unique' => __('filament-resources.territory.validation_name_unique'),
                            ]),
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
                                            ->defaultLocation(...MapCenter::resolve(static::getMapCenterZoneId($schema)))
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
                                                $alt = e(static::getImageAlt($get('name')));

                                                // The upload field holds its files keyed by an id
                                                if (is_array($file)) {
                                                    $file = Arr::first($file);
                                                }

                                                if ($file instanceof TemporaryUploadedFile) {
                                                    // Livewire throws for the files it cannot preview (e.g. HEIC saved as .jpg)
                                                    if (! $file->isPreviewable()) {
                                                        return null;
                                                    }

                                                    return new HtmlString(
                                                        '<img src="'.e($file->temporaryUrl()).'" alt="'.$alt.'" class="rounded-lg">'
                                                    );
                                                }

                                                // A stored image, moved to the upload field when the form is filled
                                                $url = filled($file) ? $file : $get('image_path');

                                                if (! filled($url)) {
                                                    return null;
                                                }

                                                if (static::isLocalImagePath($url)) {
                                                    $url = Storage::disk('local')->temporaryUrl(
                                                        $url,
                                                        now()->plus(minutes: 5)
                                                    );
                                                }

                                                return new HtmlString(
                                                    '<img src="'.e($url).'" alt="'.$alt.'" class="rounded-lg">'
                                                );
                                            })
                                            ->live(),

                                        TextInput::make('image_path')
                                            ->label(__('filament-resources.territory.field_image_url'))
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
                                            ->state(__('filament-resources.territory.image_separator'))
                                            ->hiddenOn(['view'])
                                            ->extraAttributes([
                                                'class' => 'flex items-center justify-center h-full',
                                                'aria-hidden' => 'true',
                                            ]),
                                        FileUpload::make('image_file')
                                            ->label(__('filament-resources.territory.field_image_file'))
                                            ->image()
                                            // Not SVG, which can carry scripts run when the image is opened
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
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
                        TextEntry::make('average_coverage_time')
                            ->label(__('filament-resources.territory.field_average_coverage_time'))
                            ->visibleOn('view')
                            ->state(fn (Territory $record): ?string => static::formatAverageCoverageTime($record->averageCoverageDays()))
                            ->placeholder(__('filament-resources.territory.average_coverage_time_never_covered')),
                    ]),
            ]);
    }

    /**
     * Format the average coverage time of a territory, given in days.
     */
    public static function formatAverageCoverageTime(?float $days): ?string
    {
        if ($days === null) {
            return null;
        }

        $roundedDays = (int) round($days);

        if ($roundedDays <= 0) {
            return __('filament-resources.territory.average_coverage_time_less_than_a_day');
        }

        return trans_choice('filament-resources.territory.assignment_duration_day', $roundedDays, ['count' => $roundedDays]);
    }

    /**
     * Number of territories of each group of the table, as filtered and searched, so that the counts in the group
     * headers match the rows below them. They are counted with a single query per request and grouping.
     *
     * @param  Closure(QueryBuilder): QueryBuilder  $selectGroupKey  selects the key of the group as `group_key`
     * @return array<array-key, int> the counts by group key, with the empty string for the territories with no key
     */
    protected static function countTableTerritoriesByGroup(HasTable $livewire, Closure $selectGroupKey): array
    {
        static::$tableTerritoriesCountsByGroup ??= new WeakMap;

        $query = $selectGroupKey($livewire->getFilteredTableQuery()->toBase()->reorder())
            ->selectRaw('count(*) as aggregate')
            ->groupBy('group_key');

        // Keyed by the query too, so that the counts follow the filters and the search if they change
        $cacheKey = md5($query->toSql().'|'.serialize($query->getBindings()));
        $countsByQuery = static::$tableTerritoriesCountsByGroup[$livewire] ?? [];

        if (! array_key_exists($cacheKey, $countsByQuery)) {
            $countsByQuery[$cacheKey] = $query
                ->get()
                ->mapWithKeys(fn (object $row): array => [(string) $row->group_key => (int) $row->aggregate])
                ->all();

            static::$tableTerritoriesCountsByGroup[$livewire] = $countsByQuery;
        }

        return $countsByQuery[$cacheKey];
    }

    /**
     * Name of the campaign of the open assignment of the territories of the given table, as a subquery. A subquery
     * rather than a join: the query keeps reading the territories table only, so the columns of the table searches
     * (e.g. `name`) are not ambiguous.
     */
    protected static function currentCampaignNameQuery(string $territoriesTable): QueryBuilder
    {
        return Campaign::query()
            ->select((new Campaign)->qualifyColumn('name'))
            ->whereHas('assignments', fn (Builder $assignments): Builder => $assignments
                ->whereColumn($assignments->qualifyColumn('territory_id'), "{$territoriesTable}.id")
                ->whereNull($assignments->qualifyColumn('ended_at')))
            ->limit(1)
            ->toBase();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('territoryZone.name')
                    ->label(ucfirst(__('filament-resources.territory_zone.model_label')))
                    ->getTitleFromRecordUsing(function (Territory $record, HasTable $livewire): string {
                        $territoriesCountByZone = static::countTableTerritoriesByGroup(
                            $livewire,
                            fn (QueryBuilder $query): QueryBuilder => $query->select($query->from.'.territory_zone_id as group_key'),
                        );

                        return $record->territoryZone->name.' ('.($territoriesCountByZone[$record->territory_zone_id] ?? 0).')';
                    })
                    ->getDescriptionFromRecordUsing(fn (Territory $record): string => $record->territoryZone->description ?? '')
                    ->collapsible(),
                Group::make('currentAssignment.campaign.name')
                    ->label(ucfirst(__('filament-resources.campaign.model_label')))
                    ->getTitleFromRecordUsing(function (Territory $record, HasTable $livewire): string {
                        $territoriesCountByCampaign = static::countTableTerritoriesByGroup(
                            $livewire,
                            fn (QueryBuilder $query): QueryBuilder => $query
                                ->select([])
                                ->selectSub(static::currentCampaignNameQuery($query->from), 'group_key'),
                        );

                        /** @var Campaign|null $campaign */
                        $campaign = $record->currentAssignment?->campaign;
                        $title = $campaign?->name ?? __('filament-resources.territory.filter_no_campaign');

                        return $title.' ('.($territoriesCountByCampaign[$campaign?->name ?? ''] ?? 0).')';
                    })
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        $currentCampaignName = static::currentCampaignNameQuery($query->getModel()->getTable());

                        return $query->orderByRaw(
                            "COALESCE(({$currentCampaignName->toSql()}), 'zzz_no_campaign') ".($direction === 'desc' ? 'desc' : 'asc'),
                            $currentCampaignName->getBindings(),
                        );
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
                            ->orderByRaw(
                                'CASE WHEN '.$query->getQuery()->getGrammar()->wrap('duration_assignments.started_at').' IS NULL THEN 0 ELSE 1 END '
                                .($direction === 'desc' ? 'desc' : 'asc')
                            )
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
                    static::explainRefusedDeletion(ForceDeleteAction::make())
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
                                } catch (ValidationException $exception) {
                                    // Rejected by TerritoryAssignmentObserver (e.g. the territory has been assigned in the meantime)
                                    Log::warning("Skipping record {$record->id}: {$exception->getMessage()}");
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

                                try {
                                    $currentAssignment->update([
                                        'ended_at' => now(),
                                    ]);
                                } catch (ValidationException $exception) {
                                    // Rejected by TerritoryAssignmentObserver (e.g. the assignment starts in the future)
                                    Log::warning("Skipping record {$record->id}: {$exception->getMessage()}");
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    static::explainRefusedDeletion(DeleteBulkAction::make())
                        ->deselectRecordsAfterCompletion(),
                    static::explainRefusedDeletion(ForceDeleteBulkAction::make())
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
     * Whether a territory image is a path on the local disk rather than a URL: it has no scheme (`https:`, `ftp:`...)
     * and is not protocol-relative (`//cdn...`).
     */
    public static function isLocalImagePath(?string $imagePath): bool
    {
        return filled($imagePath)
            && ! Str::of($imagePath)->isMatch('/^([a-z][a-z0-9+.\-]*:|\/\/)/i');
    }

    /**
     * Move an image stored on the local disk (`image_path`) to the upload field (`image_file`), so that the
     * `image_path` field, which only accepts URLs, keeps only external images. Anything else (URLs, missing files)
     * stays in `image_path`, so that it is not lost when the territory is saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitStoredImageFromFormData(array $data): array
    {
        $imagePath = $data['image_path'] ?? null;

        if (static::isLocalImagePath($imagePath) && Storage::disk('local')->exists($imagePath)) {
            $data['image_file'] = $imagePath;
            $data['image_path'] = null;
        }

        return $data;
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

    /**
     * The alternative text of a territory image: the territory name, or a generic one while it has none.
     */
    public static function getImageAlt(?string $territoryName): string
    {
        return filled($territoryName) ? $territoryName : __('filament-resources.territory.image_alt');
    }

    /**
     * Explain why a territory has not been deleted: {@see TerritoryObserver::deleting()}
     * refuses to (force) delete a territory that is still assigned.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action  A (force) delete action, single or bulk
     * @return TAction
     */
    public static function explainRefusedDeletion(Action $action): Action
    {
        if ($action instanceof BulkAction) {
            return $action->missingBulkProcessingFailureNotificationMessage(
                fn (int $count): string => trans_choice('filament-resources.territory.delete_refused_bulk', $count),
            );
        }

        return $action
            ->failureNotificationTitle(__('filament-resources.territory.delete_refused_title'))
            ->failureNotificationBody(__('filament-resources.territory.delete_refused_body'));
    }
}
