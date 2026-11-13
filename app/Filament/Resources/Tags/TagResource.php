<?php

namespace App\Filament\Resources\Tags;

use App\Enums\NavigationGroup;
use App\Enums\TagType;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Tag;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'type'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name.($record->type ? ' ('.$record->type.')' : '');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            ...($record->type ? [__('filament-resources.global_search_results.type') => $record->type] : []),
            ...($record->slug ? [__('filament-resources.global_search_results.slug') => $record->slug] : []),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('filament-resources.tag.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.tag.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Administration;
    }

    public static function form(Schema $schema): Schema
    {
        $locale = Tag::getLocale();

        return $schema
            ->components([
                // Tags are stored in a single locale, whatever the interface language (see Tag::getLocale())
                TextInput::make("name.{$locale}")
                    ->label(__('filament-resources.tag.field_name'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set) use ($locale): void {
                        if ($operation !== 'create' || ! $state) {
                            return;
                        }

                        $set("slug.{$locale}", Str::slug($state));
                    }),
                TextInput::make("slug.{$locale}")
                    ->label(__('filament-resources.tag.field_slug'))
                    ->required()
                    ->maxLength(255)
                    ->rules(['alpha_dash']),
                Select::make('type')
                    ->label(__('filament-resources.tag.field_type'))
                    ->options(TagType::getOptions())
                    ->nullable()
                    ->searchable(),
                TextInput::make('order_column')
                    ->label(__('filament-resources.tag.field_order'))
                    ->numeric()
                    ->nullable()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.tag.column_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('filament-resources.tag.column_slug'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('type')
                    ->label(__('filament-resources.tag.column_type'))
                    ->badge()
                    ->color(fn (string $state): string => TagType::tryFrom($state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => TagType::tryFrom($state)?->getLabel() ?? ($state ?: __('filament-resources.tag.type_none')))
                    ->sortable(),
                TextColumn::make('taggables_count')
                    ->label(__('filament-resources.tag.column_usage_count'))
                    ->getStateUsing(fn (Tag $record): int => $record->taggables()->count())
                    ->sortable(false)
                    ->alignCenter(),
                TextColumn::make('order_column')
                    ->label(__('filament-resources.tag.column_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('filament-resources.tag.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-resources.tag.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('filament-resources.tag.filter_type'))
                    ->options(TagType::getOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }

    public static function canGloballySearch(): bool
    {
        return true;
    }
}
