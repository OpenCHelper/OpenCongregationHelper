<?php

namespace App\Filament\Resources\TerritoryAssignments;

use App\Enums\NavigationGroup;
use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Models\TerritoryAssignment;
use App\Rules\NotTerminatedCampaign;
use App\Rules\TerritoryNotAlreadyAssigned;
use App\Settings\TerritorySettings;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Infolists\MapEntry;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Polygon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Override;
use UnitEnum;

class TerritoryAssignmentResource extends Resource
{
    protected static ?string $model = TerritoryAssignment::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::RectangleStack;

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

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Territories;
    }

    #[Override]
    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    /**
     * Number of overdue assignments (in progress although their campaign has ended) the user can see.
     */
    public static function getNavigationBadge(): ?string
    {
        $overdueCount = static::getOverdueAssignmentsQuery()->count();

        return $overdueCount > 0 ? (string) $overdueCount : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('filament-resources.territory_assignment.navigation_badge_tooltip');
    }

    /**
     * The assignments the current user is allowed to see, ignoring the deleted ones.
     *
     * @return Builder<TerritoryAssignment>
     */
    public static function getVisibleAssignmentsQuery(): Builder
    {
        return static::scopeToVisibleAssignments(TerritoryAssignment::query()->whereHas('assignee'));
    }

    /**
     * The assignments the current user is allowed to see that are still in progress although their campaign has ended.
     *
     * @return Builder<TerritoryAssignment>
     */
    public static function getOverdueAssignmentsQuery(): Builder
    {
        return static::getVisibleAssignmentsQuery()->overdue();
    }

    /**
     * Users allowed to list all the assignments see everyone's assignments,
     * the others only see their own (e.g. when opening a notification link).
     */
    public static function canViewAllAssignments(): bool
    {
        return Gate::allows('viewAny', TerritoryAssignment::class);
    }

    /**
     * Personal data (assignee name, notes) is hidden on publicly shared pages
     * unless enabled from the Settings pages (or with `ENABLE_SHARED_PII`).
     */
    public static function shouldHidePersonalData(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'guest'
            && ! app(TerritorySettings::class)->shared_pii;
    }

    /**
     * Color highlighting an assignment still in progress whose campaign has ended (or is about to).
     */
    public static function getCampaignDeadlineColor(TerritoryAssignment $record): ?string
    {
        return match (true) {
            $record->isOverdue() => 'danger',
            $record->isDueSoon() => 'warning',
            default => null,
        };
    }

    /**
     * Time between now and the end of the campaign of the assignment, rounded and without direction (e.g. "2 days"):
     * the translations add it ("in 2 days", "2 days ago"), so that each language can phrase it naturally.
     *
     * Under two weeks the time is counted in days ("10 days" rather than "1 week"), since every day counts there.
     */
    public static function getCampaignDeadlineDistance(TerritoryAssignment $record): string
    {
        $validUntil = $record->campaign->valid_until;
        $days = (int) round(abs(now()->diffInDays($validUntil)));

        if ($days >= 1 && $days < 14) {
            return CarbonInterval::days($days)->forHumans(['skip' => 'week']);
        }

        return $validUntil->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, options: CarbonInterface::ROUND);
    }

    /**
     * Description of when the campaign of an assignment still in progress has ended (or is going to end).
     */
    public static function getCampaignDeadlineDescription(TerritoryAssignment $record): ?string
    {
        return match (true) {
            $record->isOverdue() => __('filament-resources.territory_assignment.campaign_ended', ['when' => static::getCampaignDeadlineDistance($record)]),
            $record->isDueSoon() => __('filament-resources.territory_assignment.campaign_ending', ['when' => static::getCampaignDeadlineDistance($record)]),
            default => null,
        };
    }

    /**
     * Banner reminding to return an assignment whose campaign has ended (or is about to end).
     */
    public static function getCampaignDeadlineCallout(): Callout
    {
        return Callout::make(fn (TerritoryAssignment $record): ?string => match (true) {
            $record->isOverdue() => __('filament-resources.territory_assignment.overdue_assignment_heading', [
                'campaign' => $record->campaign->name,
                'when' => static::getCampaignDeadlineDistance($record),
                'date' => $record->campaign->valid_until->isoFormat('LL'),
            ]),
            $record->isDueSoon() => __('filament-resources.territory_assignment.due_soon_assignment_heading', [
                'campaign' => $record->campaign->name,
                'when' => static::getCampaignDeadlineDistance($record),
                'date' => $record->campaign->valid_until->isoFormat('LL'),
            ]),
            default => null,
        })
            ->key('campaignDeadlineCallout')
            ->description(__('filament-resources.territory_assignment.campaign_deadline_callout_description'))
            ->icon(fn (TerritoryAssignment $record): Heroicon => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedClock)
            ->color(fn (TerritoryAssignment $record): ?string => static::getCampaignDeadlineColor($record))
            ->visible(fn (TerritoryAssignment $record): bool => $record->isOverdue() || $record->isDueSoon())
            ->columnSpanFull()
            ->actions([
                Action::make('returnAssignment')
                    ->button()
                    ->label(__('filament-resources.territory_assignment.action_end_assignment'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color(fn (TerritoryAssignment $record): ?string => static::getCampaignDeadlineColor($record))
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-resources.territory_assignment.action_end_assignment'))
                    ->modalSubmitActionLabel(__('filament-resources.territory_assignment.action_end'))
                    ->authorize(fn (TerritoryAssignment $record): bool => Gate::allows('update', $record))
                    ->action(function (TerritoryAssignment $record): void {
                        $record->update(['ended_at' => now()]);

                        Notification::make()
                            ->success()
                            ->title(__('filament-resources.territory_assignment.return_success'))
                            ->send();
                    }),
            ]);
    }

    /**
     * Banner telling that the return of the assignment has been asked to the territory department, with the button to
     * close it for those who can.
     */
    public static function getReturnRequestedCallout(): Callout
    {
        return Callout::make(fn (TerritoryAssignment $record): ?string => $record->isReturnRequested()
            ? __('filament-resources.territory_assignment.return_requested_heading', [
                'date' => $record->return_requested_at->isoFormat('LLL'),
            ])
            : null)
            ->key('returnRequestedCallout')
            ->description(__('filament-resources.territory_assignment.return_requested_description'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->info()
            ->visible(fn (TerritoryAssignment $record): bool => $record->isReturnRequested())
            ->columnSpanFull()
            ->actions([
                Action::make('returnRequestedAssignment')
                    ->button()
                    ->label(__('filament-resources.territory_assignment.action_end_assignment'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-resources.territory_assignment.action_end_assignment'))
                    ->modalSubmitActionLabel(__('filament-resources.territory_assignment.action_end'))
                    ->authorize(fn (TerritoryAssignment $record): bool => Gate::allows('update', $record))
                    ->action(function (TerritoryAssignment $record): void {
                        $record->update(['ended_at' => now()]);

                        Notification::make()
                            ->success()
                            ->title(__('filament-resources.territory_assignment.return_success'))
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::getReturnRequestedCallout(),
                static::getCampaignDeadlineCallout(),
                Section::make(__('filament-resources.territory_assignment.section_territory_info'))
                    ->description(__('filament-resources.territory_assignment.section_territory_info_description'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('territory.name')
                            ->label(__('filament-resources.territory_assignment.info_territory'))
                            // Shown when the territory (or its zone) has been deleted while still assigned
                            ->placeholder(__('filament-resources.territory_assignment.info_territory_placeholder')),
                        TextEntry::make('territory.territoryZone.name')
                            ->label(__('filament-resources.territory_assignment.info_zone')),
                        Tabs::make('Tabs')
                            ->columnSpanFull()
                            ->hidden(fn (Model $record) => is_null($record->territory?->image_path) && is_null($record->territory?->geojson))
                            ->tabs([
                                Tab::make('TabMap')
                                    ->label(__('filament-resources.territory.tab_map'))
                                    ->icon(Heroicon::OutlinedMap)
                                    ->visible(fn (TerritoryAssignment $record) => $record->territory?->geojson != null)
                                    ->schema([
                                        MapEntry::make('map')
                                            ->hiddenLabel()
                                            ->label(__('filament-resources.territory_assignment.info_location'))
                                            ->id('assignment-map')
                                            ->state(null)
                                            ->columnSpanFull()
                                            ->visible(fn (TerritoryAssignment $record) => $record->territory?->geojson != null)
                                            ->fitBounds()
                                        // ->zoom(10)
                                            ->recenterTimeout(null)
                                            ->mapDraggable(true)
                                            ->mapZoomable(true)
                                            ->fullscreenControl()
                                            ->tileLayersUrl([
                                                'Street Map' => TileLayer::OpenStreetMap,
                                                'Google Streets' => TileLayer::GoogleStreets,
                                                'Google Satellite' => TileLayer::GoogleSatellite,
                                                'Google Hybrid' => TileLayer::GoogleHybrid,
                                            ])
                                            ->shapes(function ($record): array {
                                                $territory = $record->territory;
                                                $geojson = json_decode($territory->geojson, true);

                                                $polygons = [];
                                                foreach ($geojson['features'] as $feature) {
                                                    if ($feature['geometry']['type'] != 'Polygon') {
                                                        continue;
                                                    }

                                                    // The first two elements are longitude and latitude, but we need latitude and longitude
                                                    // Swap all the coordinates
                                                    $coords_lat_long = array_map(function ($coord) {
                                                        return array_map(function ($c) {
                                                            return [$c[1], $c[0]];
                                                        }, $coord);
                                                    }, $feature['geometry']['coordinates']);

                                                    $popup = '';
                                                    $polygons[] = Polygon::make($coords_lat_long)
                                                        ->fillColor($territory->territoryZone->color)
                                                        ->fillOpacity(0.3);
                                                }

                                                return $polygons;
                                            }),
                                    ]),
                                Tab::make('TabImage')
                                    ->label(__('filament-resources.territory.tab_image'))
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->visible(fn (Model $record) => $record->territory?->image_path != null)
                                    ->schema([
                                        ImageEntry::make('territory.image_path')
                                            ->visibility('private')
                                            ->imageHeight('auto')
                                            ->imageWidth('100%')
                                            ->hiddenLabel()
                                            ->visible(fn (Model $record) => $record->territory?->image_path != null)
                                            ->columnSpanFull(),
                                    ]),

                            ]),
                        TextEntry::make('territory.description')
                            ->label(__('filament-resources.territory_assignment.info_description'))
                            ->columnSpanFull()
                            ->visible(fn (Model $record) => $record->territory?->description != null)
                            ->placeholder(__('filament-resources.territory_assignment.info_description_placeholder')),
                        TextEntry::make('territory.notes')
                            ->label(__('filament-resources.territory_assignment.info_notes'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->columnSpanFull()
                            ->hidden(fn (): bool => static::shouldHidePersonalData())
                            ->placeholder(__('filament-resources.territory_assignment.info_notes_placeholder')),
                    ]),
                Section::make(__('filament-resources.territory_assignment.section_assignment_info'))
                    ->description(__('filament-resources.territory_assignment.section_assignment_info_description'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('assignee.full_name')
                            ->icon(Heroicon::OutlinedUser)
                            ->hidden(fn (): bool => static::shouldHidePersonalData())
                            ->label(__('filament-resources.territory_assignment.info_assignee')),
                        TextEntry::make('campaign.name')
                            ->icon(Heroicon::OutlinedEnvelopeOpen)
                            ->label(__('filament-resources.territory_assignment.info_campaign'))
                            ->placeholder(__('filament-resources.territory_assignment.info_campaign_placeholder')),
                        TextEntry::make('started_at')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->isoDateTime()
                            ->sinceTooltip()
                            ->label(__('filament-resources.territory_assignment.info_start_assignment')),
                        TextEntry::make('ended_at')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->isoDateTime()
                            ->sinceTooltip()
                            ->visible(fn (Model $record) => $record->ended_at != null)
                            ->label(__('filament-resources.territory_assignment.info_end_assignment'))
                            ->placeholder(__('filament-resources.territory_assignment.info_end_assignment_placeholder')),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-resources.territory_assignment.section_base_info'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('territory_id')
                            ->label(__('filament-resources.territory_assignment.field_territory'))
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->relationship(
                                name: 'territory',
                                titleAttribute: 'name',
                                // Only territories that are not assigned, plus the territory of the assignment
                                // being edited: otherwise its own value would be rejected as invalid.
                                modifyQueryUsing: fn (Builder $query, ?TerritoryAssignment $record) => $query->where(
                                    fn (Builder $query) => $query
                                        ->doesntHave('assignments', 'and', fn (Builder $query) => $query->whereNull('ended_at'))
                                        ->when($record?->territory_id, fn (Builder $query, int $territoryId) => $query->orWhere($query->getModel()->getQualifiedKeyName(), $territoryId))
                                )
                            )
                            ->required()
                            ->preload(),
                        Select::make('assignee_id')
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
                Section::make(__('filament-resources.territory_assignment.section_main_info'))
                    ->columns(2)
                    ->schema([
                        Select::make('campaign_id')
                            ->label(__('filament-resources.territory_assignment.field_campaign'))
                            ->searchable()
                            ->disabledOn('edit', 'view')
                            ->relationship(
                                name: 'campaign',
                                titleAttribute: 'name',
                                // Only campaigns that are not terminated, plus the campaign of the assignment being
                                // edited: otherwise an assignment of a terminated campaign could not be saved anymore.
                                modifyQueryUsing: fn (Builder $query, ?TerritoryAssignment $record) => $query->where(
                                    fn (Builder $query) => $query
                                        ->notTerminated()
                                        ->when($record?->campaign_id, fn (Builder $query, int $campaignId) => $query->orWhere($query->getModel()->getQualifiedKeyName(), $campaignId))
                                )
                            )
                            ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign'))
                            ->rule(fn (?TerritoryAssignment $record): NotTerminatedCampaign => new NotTerminatedCampaign(
                                ignoredCampaignId: $record?->campaign_id,
                            ))
                            ->preload(),
                        DateTimePicker::make('started_at')
                            ->label(__('filament-resources.territory_assignment.field_started_at'))
                            ->default(now())
                            ->required()
                            ->beforeOrEqual('now')
                            ->validationMessages([
                                'before_or_equal' => __('validation.territory_assignment.started_in_future'),
                            ])
                            ->columnSpan(1),
                        DateTimePicker::make('ended_at')
                            ->label(__('filament-resources.territory_assignment.field_ended_at'))
                            ->afterOrEqual('started_at')
                            ->beforeOrEqual('now')
                            ->validationMessages([
                                'after_or_equal' => __('validation.territory_assignment.ended_before_started'),
                                'before_or_equal' => __('validation.territory_assignment.ended_in_future'),
                            ])
                            // Leaving the end date empty requires the territory to have no other assignment in progress
                            ->rule(fn (?TerritoryAssignment $record, Get $get): TerritoryNotAlreadyAssigned => new TerritoryNotAlreadyAssigned(
                                territoryId: filled($territoryId = $record?->territory_id ?? $get('territory_id')) ? (int) $territoryId : null,
                                ignoredAssignmentId: $record?->getKey(),
                            ))
                            ->columnSpan(1),
                    ]),
                Section::make(__('filament-resources.territory_assignment.section_additional_info'))
                    ->description(__('filament-resources.territory_assignment.section_additional_info_description'))
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('filament-resources.territory_assignment.field_notes'))
                            ->columnSpanFull(),
                        Select::make('created_by')
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
            // Accent border on the assignments still in progress of an ended (or ending) campaign (see the panel theme)
            ->recordClasses(fn (TerritoryAssignment $record): ?string => match (true) {
                $record->isOverdue() => 'fi-ta-row-assignment-overdue',
                $record->isDueSoon() => 'fi-ta-row-assignment-due-soon',
                default => null,
            })
            ->columns([
                TextColumn::make('territory.name')
                    ->label(__('filament-resources.territory_assignment.column_territory'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assignee.full_name')
                    ->label(__('filament-resources.territory_assignment.column_assignee'))
                    ->hidden(! static::canViewAllAssignments())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('started_at')
                    ->label(__('filament-resources.territory_assignment.column_started_at'))
                    ->isoDateTime()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label(__('filament-resources.territory_assignment.column_ended_at'))
                    ->isoDateTime()
                    ->placeholder(__('filament-resources.territory_assignment.column_ended_at_placeholder'))
                    ->sortable(),
                TextColumn::make('campaign.name')
                    ->label(__('filament-resources.territory_assignment.column_campaign'))
                    ->placeholder(__('filament-resources.territory_assignment.column_campaign_placeholder'))
                    // Make the assignments still in progress of an ended (or ending) campaign stand out
                    ->icon(fn (TerritoryAssignment $record): ?Heroicon => match (true) {
                        $record->isOverdue() => Heroicon::OutlinedExclamationTriangle,
                        $record->isDueSoon() => Heroicon::OutlinedClock,
                        default => null,
                    })
                    ->color(fn (TerritoryAssignment $record): ?string => static::getCampaignDeadlineColor($record))
                    ->iconColor(fn (TerritoryAssignment $record): ?string => static::getCampaignDeadlineColor($record))
                    ->description(fn (TerritoryAssignment $record): ?string => static::getCampaignDeadlineDescription($record))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label(__('filament-resources.territory_assignment.column_creator'))
                    ->sortable(['first_name', 'last_name'])
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('filament-resources.territory_assignment.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-resources.territory_assignment.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('filament-resources.territory_assignment.column_deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('ended_at')
                    ->label(__('filament-resources.territory_assignment.filter_assignment_status'))
                    ->default(false)
                    ->nullable()
                    ->placeholder(__('filament-resources.territory_assignment.filter_assignment_status_all'))
                    ->trueLabel(__('filament-resources.territory_assignment.filter_assignment_status_returned'))
                    ->falseLabel(__('filament-resources.territory_assignment.filter_assignment_status_in_progress')),
                Filter::make('assignee')
                    ->label(__('filament-resources.territory_assignment.filter_my_assignments_only'))
                    ->query(fn (Builder $query) => $query->where('assignee_id', Auth::user()->id))
                    ->hidden(! static::canViewAllAssignments()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTerritoryAssignments::route('/'),
            'view' => ViewTerritoryAssignment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToVisibleAssignments(
            parent::getEloquentQuery()
                ->whereHas('assignee')
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
        );
    }

    /**
     * Restrict the query to the assignments the current user is allowed to see.
     *
     * Pages listing assignments through a relationship (e.g. the assignments of
     * a territory) must apply this too, since they do not use `getEloquentQuery()`.
     *
     * @template TQuery of Builder|HasMany
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function scopeToVisibleAssignments(Builder|HasMany $query): Builder|HasMany
    {
        if (static::canViewAllAssignments()) {
            return $query;
        }

        return $query->where('assignee_id', Auth::id());
    }
}
