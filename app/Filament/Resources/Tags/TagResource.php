<?php

namespace App\Filament\Resources\Tags;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Enums\TagType;
use App\Filament\Resources\TagResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tag;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
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

    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->name . ($record->type ? ' (' . $record->type . ')' : '');
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

    public static function getNavigationGroup(): ?string
    {
        return __('filament-resources.tag.navigation_group');
    }

    /**
     * Generate language tabs for the form, based on available locales.
     *
     * @return array<\Filament\Schemas\Components\Tabs\Tab>
     */
    protected static function generateLanguageTabs(): array
    {
        $availableLocales = config('app.available_locales', []);
        $tabs = [];

        foreach ($availableLocales as $locale => $localeName) {
            $isRequired = $locale === config('app.fallback_locale', 'en');
            
            $tabs[] = Tab::make($locale)
                ->icon(new HtmlString(
                    '<img src="' . asset("vendor/blade-flags/language-{$locale}.svg") . '" alt="' . $localeName . '" class="h-5 w-5 inline-block mr-2" />'
                ))
                ->label($localeName . ' (' . $locale . ')')
                ->schema([
                    TextInput::make("name.{$locale}")
                        ->label(__('filament-resources.tag.field_name') . " ({$locale})")
                        ->required($isRequired)
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Set $set) use ($locale, $availableLocales) {
                            if ($operation !== 'create' || !$state) {
                                return;
                            }
                            $slug = Str::slug($state);
                            
                            // If this is the main language (fallback), set slug for all languages
                            if ($locale === config('app.fallback_locale', 'en')) {
                                foreach (array_keys($availableLocales) as $availableLocale) {
                                    $set("slug.{$availableLocale}", $slug);
                                }
                            } else {
                                // Otherwise, just set for current language
                                $set("slug.{$locale}", $slug);
                            }
                        }),
                    TextInput::make("slug.{$locale}")
                        ->label(__('filament-resources.tag.field_slug') . " ({$locale})")
                        ->required($isRequired)
                        ->maxLength(255)
                        ->rules(['alpha_dash']),
                ])->columns(2);
        }

        return $tabs;
    }

    public static function form(Schema $schema): Schema
    {
        $tabs = self::generateLanguageTabs();

        return $schema
            ->components([
                Tabs::make('Translations')
                    ->columnSpanFull()
                    ->activeTab(function () use ($tabs) {
                        // Workaround for Filament's Tabs component to set the active tab based on the current locale
                        // This assumes that the tab IDs are suffixed with the locale, e.g., '-en-tab', '-fr-tab' 
                        // (as generated in generateLanguageTabs).
                        return collect($tabs)->search(
                            fn (Tab $tab) => Str::endsWith($tab->getId(), app()->getLocale() . '-tab')
                        ) + 1 ?: 1;
                    })
                    ->tabs($tabs),
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
