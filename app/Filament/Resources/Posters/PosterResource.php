<?php

namespace App\Filament\Resources\Posters;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Posters\Pages\ManagePosters;
use App\Filament\Resources\StorageLocations\StorageLocationResource;
use App\Models\Poster;
use App\Models\PosterStock;
use App\Models\StorageLocation;
use App\Support\Translations;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Override;
use UnitEnum;

/**
 * The inventory of the magnetic posters of the displays used in public witnessing.
 */
class PosterResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Poster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Photo;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('filament-resources.poster.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.poster.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-resources.poster.navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::PublicWitnessing;
    }

    public static function getGloballySearchableAttributes(): array
    {
        // In every language, so that a title missing in the language of the user (shown in another one) is found too
        return [...array_map(fn (string $locale): string => "title->{$locale}", array_keys(Translations::getLocales())), 'code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->title.($record->code ? " ({$record->code})" : '');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'sm' => 3])
                    ->schema([
                        TextInput::make('code')
                            ->label(__('filament-resources.poster.field_code'))
                            ->placeholder(__('filament-resources.poster.field_code_placeholder'))
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                    ]),
                Translations::inputs('title', __('filament-resources.poster.field_title')),
                Section::make(__('filament-resources.poster.section_stocks'))
                    ->description(__('filament-resources.poster.section_stocks_description'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->compact()
                    ->schema([
                        Repeater::make('stocks')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make(__('filament-resources.poster.field_location'))->markAsRequired(),
                                TableColumn::make(__('filament-resources.poster.field_quantity'))->markAsRequired(),
                            ])
                            ->compact()
                            ->schema([
                                Select::make('storage_location_id')
                                    ->label(__('filament-resources.poster.field_location'))
                                    ->relationship('storageLocation', 'name')
                                    ->createOptionForm(StorageLocationResource::getFormFields())
                                    ->createOptionModalHeading(__('filament-resources.storage_location.create_heading'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->preload()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label(__('filament-resources.poster.field_quantity'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(9999)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->defaultItems(fn (): int => StorageLocation::query()->exists() ? 1 : 0)
                            ->addActionLabel(__('filament-resources.poster.action_add_location')),
                    ]),
                FileUpload::make('image_path')
                    ->label(__('filament-resources.poster.field_image'))
                    ->helperText(__('filament-resources.poster.field_image_helper'))
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('posters')
                    ->visibility('public')
                    ->maxSize(5120),
                Textarea::make('notes')
                    ->label(__('filament-resources.poster.field_notes'))
                    ->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('stocks'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('title->'.app()->getLocale()))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('filament-resources.poster.column_image'))
                    ->disk('public')
                    ->visibility('public')
                    ->imageSize(48)
                    // Shown only once a picture has been uploaded, to keep the table compact
                    ->visible(fn (): bool => Poster::query()->whereNotNull('image_path')->exists())
                    ->toggleable(),
                TextColumn::make('code')
                    ->label(__('filament-resources.poster.column_code'))
                    ->badge()
                    ->color('gray')
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('filament-resources.poster.column_title'))
                    ->weight(FontWeight::Medium)
                    ->description(fn (Poster $record): ?string => $record->notes)
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => Translations::search($query, 'title', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('title->'.app()->getLocale(), $direction)),
                ...static::getLocationColumns(),
                TextColumn::make('total_quantity')
                    ->label(__('filament-resources.poster.column_total'))
                    ->state(fn (Poster $record): int => $record->totalQuantity())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        PosterStock::query()->selectRaw('coalesce(sum(quantity), 0)')->whereColumn('poster_id', 'posters.id'),
                        $direction,
                    ))
                    ->summarize(static::sumOfStocks()),
            ])
            ->filters([
                SelectFilter::make('location')
                    ->label(__('filament-resources.poster.filter_location'))
                    ->options(fn (): array => StorageLocation::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $locationId): Builder => $query->whereHas(
                            'stocks',
                            fn (Builder $query): Builder => $query->where('storage_location_id', $locationId)->where('quantity', '>', 0),
                        ),
                    )),
                TernaryFilter::make('available')
                    ->label(__('filament-resources.poster.filter_available'))
                    ->trueLabel(__('filament-resources.poster.filter_available_true'))
                    ->falseLabel(__('filament-resources.poster.filter_available_false'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('stocks', fn (Builder $query): Builder => $query->where('quantity', '>', 0)),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('stocks', fn (Builder $query): Builder => $query->where('quantity', '>', 0)),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateHeading(__('filament-resources.poster.empty_heading'))
            ->emptyStateDescription(__('filament-resources.poster.empty_description'));
    }

    /**
     * A column with the copies kept in each location, like the columns of a spreadsheet.
     *
     * @return list<Column>
     */
    protected static function getLocationColumns(): array
    {
        return StorageLocation::query()
            ->orderBy('name')
            ->get()
            ->map(fn (StorageLocation $location): Column => TextColumn::make("location_{$location->getKey()}")
                ->label($location->name)
                ->state(fn (Poster $record): int => $record->quantityIn($location))
                ->color(fn (int $state): ?string => $state > 0 ? null : 'gray')
                ->alignCenter()
                ->toggleable()
                ->summarize(static::sumOfStocks($location)))
            ->all();
    }

    /**
     * Sum the copies of the posters shown by the table (all the filtered ones), in one or every location.
     */
    protected static function sumOfStocks(?StorageLocation $location = null): Summarizer
    {
        return Summarizer::make('total')
            ->label(__('filament-resources.poster.summary_total'))
            ->using(fn (QueryBuilder $query): int => (int) PosterStock::query()
                ->whereIn('poster_id', $query->pluck('id'))
                ->when($location, fn (Builder $stocks) => $stocks->where('storage_location_id', $location->getKey()))
                ->sum('quantity'));
    }

    public static function getDocumentation(): array
    {
        return [
            'public-witnessing.posters',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePosters::route('/'),
        ];
    }
}
