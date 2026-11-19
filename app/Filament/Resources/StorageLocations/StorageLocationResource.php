<?php

namespace App\Filament\Resources\StorageLocations;

use App\Enums\NavigationGroup;
use App\Filament\Resources\StorageLocations\Pages\ManageStorageLocations;
use App\Models\StorageLocation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Override;
use UnitEnum;

/**
 * The places where the posters are kept, e.g. the Kingdom Hall or a storage box.
 */
class StorageLocationResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = StorageLocation::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::ArchiveBox;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('filament-resources.storage_location.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.storage_location.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::PublicWitnessing;
    }

    /**
     * The fields of a location, also used to create one from the poster form.
     *
     * @return list<TextInput|Textarea>
     */
    public static function getFormFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('filament-resources.storage_location.field_name'))
                ->placeholder(__('filament-resources.storage_location.field_name_placeholder'))
                ->required()
                ->maxLength(255)
                ->unique(StorageLocation::class, 'name', ignoreRecord: true),
            Textarea::make('notes')
                ->label(__('filament-resources.storage_location.field_notes'))
                ->placeholder(__('filament-resources.storage_location.field_notes_placeholder'))
                ->rows(2),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(static::getFormFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount(['posterStocks' => fn ($query) => $query->where('quantity', '>', 0)])
                ->withSum('posterStocks', 'quantity'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.storage_location.column_name'))
                    ->icon(Heroicon::OutlinedMapPin)
                    ->description(fn (StorageLocation $record): ?string => $record->notes)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('poster_stocks_count')
                    ->label(__('filament-resources.storage_location.column_posters'))
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('poster_stocks_sum_quantity')
                    ->label(__('filament-resources.storage_location.column_copies'))
                    ->default(0)
                    ->alignCenter()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('filament-resources.storage_location.delete_description')),
            ])
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)
            ->emptyStateHeading(__('filament-resources.storage_location.empty_heading'))
            ->emptyStateDescription(__('filament-resources.storage_location.empty_description'));
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
            'index' => ManageStorageLocations::route('/'),
        ];
    }
}
