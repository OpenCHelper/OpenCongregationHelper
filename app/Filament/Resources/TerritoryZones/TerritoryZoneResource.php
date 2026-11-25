<?php

namespace App\Filament\Resources\TerritoryZones;

use App\Enums\NavigationGroup;
use App\Enums\TagType;
use App\Filament\Resources\TerritoryZones\Pages\CreateTerritoryZone;
use App\Filament\Resources\TerritoryZones\Pages\EditTerritoryZone;
use App\Filament\Resources\TerritoryZones\Pages\ListTerritoryZones;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Filament\Resources\TerritoryZones\Pages\ViewTerritoryZone;
use App\Filament\Tables\Columns\RecordCardColumn;
use App\Models\Tag;
use App\Models\TerritoryZone;
use App\Observers\TerritoryZoneObserver;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use UnitEnum;

class TerritoryZoneResource extends Resource
{
    protected static ?string $model = TerritoryZone::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Map;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['tags']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $tags = $record->tagsWithType(TagType::TERRITORIES->value);

        return [
            ...(! $tags->isEmpty() ? [__('filament-resources.global_search_results.tags') => $tags->map(fn ($tag) => $tag->name)->join(',')] : []),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('filament-resources.territory_zone.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.territory_zone.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Territories;
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
                TextInput::make('name')
                    ->label(__('filament-resources.territory_zone.field_name'))
                    ->required()
                    ->maxLength(50),
                ColorPicker::make('color')
                    ->label(__('filament-resources.territory_zone.field_color')),
                SpatieTagsInput::make('tags')
                    ->label(__('filament-resources.territory_zone.field_tags'))
                    ->type(TagType::ZONES->value)
                    ->nestedRecursiveRules([
                        'min:3',
                        'max:255',
                    ]),
                // Forms\Components\TagsInput::make('asd')
                //     ->suggestions([
                //         'tag1',
                //         'tag2',
                //         'tag3',
                //     ])
                //     ->nestedRecursiveRules([
                //         'min:3',
                //         'max:255',
                //     ])
                //     ->label('Tags'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): string => TerritoryZoneResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.territory_zone.column_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('territories_count')
                    // Also count the territories of deleted zones, hidden by `parentNotDeleted` until the zone is restored
                    ->counts(['territories' => fn (Builder $query): Builder => $query->withoutGlobalScope('parentNotDeleted')])
                    ->label(__('filament-resources.territory_zone.column_territories_count'))
                    ->sortable(),
                SpatieTagsColumn::make('tags')
                    ->label(__('filament-resources.territory_zone.column_tags'))
                    ->type(TagType::ZONES->value)
                    ->toggleable(),
            ])
            ->tap(fn (Table $table) => RecordCardColumn::make()->heading('name')->fullWidth('tags')->addTo($table))
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('tags')
                    ->label(__('filament-resources.territory_zone.filter_tags'))
                    ->multiple()
                    ->options(Tag::getWithType(TagType::ZONES->value)->pluck('name', 'name'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['values'], function (Builder $query, $data): Builder {
                            return $query->withAnyTags(array_values($data), TagType::ZONES->value);
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                static::explainRefusedDeletion(ForceDeleteAction::make()),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::explainRefusedDeletion(DeleteBulkAction::make())->deselectRecordsAfterCompletion(),
                    static::explainRefusedDeletion(ForceDeleteBulkAction::make())->deselectRecordsAfterCompletion(),
                    RestoreBulkAction::make()->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewTerritoryZone::class,
            ManageZoneTerritories::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerritoryZones::route('/'),
            'create' => CreateTerritoryZone::route('/create'),
            'view' => ViewTerritoryZone::route('/{record}'),
            'edit' => EditTerritoryZone::route('/{record}/edit'),
            'territories' => ManageZoneTerritories::route('/{record}/territories'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Explain why a zone has not been deleted: {@see TerritoryZoneObserver::deleting()}
     * refuses to delete a zone that still has territories, and to force delete one that
     * still has territories, deleted ones included.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action  A (force) delete action, single or bulk
     * @return TAction
     */
    public static function explainRefusedDeletion(Action $action): Action
    {
        // A force delete is also refused because of the deleted territories, so it has its own explanation
        $reason = ($action instanceof ForceDeleteAction) || ($action instanceof ForceDeleteBulkAction)
            ? 'force_delete_refused'
            : 'delete_refused';

        if ($action instanceof BulkAction) {
            return $action->missingBulkProcessingFailureNotificationMessage(
                fn (int $count): string => trans_choice("filament-resources.territory_zone.{$reason}_bulk", $count),
            );
        }

        return $action
            ->failureNotificationTitle(__('filament-resources.territory_zone.delete_refused_title'))
            ->failureNotificationBody(__("filament-resources.territory_zone.{$reason}_body"));
    }
}
