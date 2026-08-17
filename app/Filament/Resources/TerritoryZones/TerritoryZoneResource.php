<?php

namespace App\Filament\Resources\TerritoryZones;

use App\Enums\NavigationGroup;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TerritoryZones\Pages\ViewTerritoryZone;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Filament\Resources\TerritoryZones\Pages\ListTerritoryZones;
use App\Filament\Resources\TerritoryZones\Pages\CreateTerritoryZone;
use App\Filament\Resources\TerritoryZones\Pages\EditTerritoryZone;
use App\Enums\TagType;
use App\Filament\Resources\TerritoryZoneResource\Pages;
use App\Filament\Resources\TerritoryZoneResource\RelationManagers;
use App\Models\TerritoryZone;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Filament\Forms;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieTagsColumn;
use Override;
use Spatie\Tags\Tag;
use UnitEnum;

class TerritoryZoneResource extends Resource
{
    protected static ?string $model = TerritoryZone::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedMap;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Map;
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
            ...(!$tags->isEmpty() ? [__('filament-resources.global_search_results.tags') => $tags->map(fn ($tag) => $tag->name)->join(',')] : []),
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
                    ->counts('territories')
                    ->label(__('filament-resources.territory_zone.column_territories_count'))
                    ->sortable(),
                SpatieTagsColumn::make('tags')
                    ->label(__('filament-resources.territory_zone.column_tags'))
                    ->type(TagType::ZONES->value),
            ])
            ->filters([
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->deselectRecordsAfterCompletion(),
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
}
