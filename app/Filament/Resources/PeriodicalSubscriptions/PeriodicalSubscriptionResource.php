<?php

namespace App\Filament\Resources\PeriodicalSubscriptions;

use App\Enums\NavigationGroup;
use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Filament\Resources\Publications\PublicationResource;
use App\Models\PeriodicalSubscription;
use App\Models\Publication;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Override;
use UnitEnum;

/**
 * The publishers receiving publications on paper, with the copies of each one.
 *
 * The publications are the ones offered in their list (`PublicationResource`): a column and a field each.
 */
class PeriodicalSubscriptionResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = PeriodicalSubscription::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Newspaper;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('filament-resources.periodical_subscription.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.periodical_subscription.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-resources.periodical_subscription.navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Publications;
    }

    public static function form(Schema $schema): Schema
    {
        $publications = Publication::offered();

        return $schema
            ->columns(1)
            ->components([
                Select::make('user_id')
                    ->label(__('filament-resources.periodical_subscription.field_user'))
                    ->relationship(
                        'user',
                        'first_name',
                        // A publisher is listed once: the ones already in the list can't be chosen again
                        modifyQueryUsing: fn (Builder $query, ?PeriodicalSubscription $record): Builder => $query
                            ->whereDoesntHave('periodicalSubscription', fn (Builder $query): Builder => $query->whereKeyNot($record?->getKey() ?? 0))
                            ->orderBy('first_name')
                            ->orderBy('last_name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->getFullName())
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => __('filament-resources.periodical_subscription.validation_already_listed'),
                    ]),
                // One row for each publication received, so that the form stays short however many are offered
                Repeater::make('items')
                    ->label(__('filament-resources.periodical_subscription.field_items'))
                    ->relationship()
                    ->table([
                        TableColumn::make(__('filament-resources.periodical_subscription.field_publication'))->markAsRequired(),
                        TableColumn::make(__('filament-resources.periodical_subscription.field_quantity'))->markAsRequired(),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('publication_id')
                            ->label(__('filament-resources.periodical_subscription.field_publication'))
                            ->options(fn (?int $state): array => static::getPublicationOptions($state))
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->distinct()
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label(__('filament-resources.periodical_subscription.field_quantity'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(99)
                            ->default(1)
                            ->required(),
                    ])
                    ->defaultItems(0)
                    ->minItems(1)
                    ->validationMessages([
                        'min' => __('filament-resources.periodical_subscription.validation_at_least_one'),
                    ])
                    ->addActionLabel(__('filament-resources.periodical_subscription.action_add_publication'))
                    ->helperText(fn (): ?string => $publications->isEmpty()
                        ? __('filament-resources.periodical_subscription.no_publications', ['list' => PublicationResource::getNavigationLabel()])
                        : null),
                Textarea::make('notes')
                    ->label(__('filament-resources.periodical_subscription.field_notes'))
                    ->placeholder(__('filament-resources.periodical_subscription.field_notes_placeholder'))
                    ->rows(2),
            ]);
    }

    /**
     * The publications that can be chosen, grouped by category: the offered ones, plus the one already chosen in the
     * row, even if no longer offered (so that its copies are kept and shown).
     *
     * @return array<string, array<int, string>>
     */
    public static function getPublicationOptions(?int $selected = null): array
    {
        return Publication::query()
            ->where(fn (Builder $query): Builder => $query->where('is_active', true)->when($selected, fn (Builder $query): Builder => $query->orWhere('id', $selected)))
            ->ordered()
            ->get()
            ->groupBy(fn (Publication $publication): string => $publication->category->getLabel())
            ->map(fn (Collection $publications): array => $publications
                ->mapWithKeys(fn (Publication $publication): array => [$publication->getKey() => $publication->is_active
                    ? $publication->name
                    : __('filament-resources.periodical_subscription.publication_not_offered', ['name' => $publication->name])])
                ->all())
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'items']))
            ->defaultSort(fn (Builder $query): Builder => static::sortByName($query, 'asc'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('user.full_name')
                    ->label(__('filament-resources.periodical_subscription.column_user'))
                    ->icon(Heroicon::OutlinedUser)
                    ->weight(FontWeight::Medium)
                    ->description(fn (PeriodicalSubscription $record): ?string => $record->notes)
                    ->wrap()
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => static::sortByName($query, $direction)),
                // The publications of each category under a common header
                ...Publication::offered()
                    ->groupBy(fn (Publication $publication): string => $publication->category->value)
                    ->map(fn (Collection $publications): ColumnGroup => ColumnGroup::make(
                        $publications->first()->category->getLabel(),
                        $publications->map(fn (Publication $publication): Column => static::getPublicationColumn($publication))->all(),
                    )->alignCenter())
                    ->values()
                    ->all(),
            ])
            ->filters([
                SelectFilter::make('publication')
                    ->label(__('filament-resources.periodical_subscription.filter_publication'))
                    // Grouped by category
                    ->options(fn (): array => Publication::offered()
                        ->groupBy(fn (Publication $publication): string => $publication->category->getLabel())
                        ->map(fn (Collection $publications): array => $publications->pluck('name', 'id')->all())
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $publicationId): Builder => $query->receiving((int) $publicationId),
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn (PeriodicalSubscription $record): string => $record->user->getFullName()),
                DeleteAction::make()
                    ->label(__('filament-resources.periodical_subscription.action_remove'))
                    ->modalHeading(fn (PeriodicalSubscription $record): string => __('filament-resources.periodical_subscription.action_remove_heading', ['name' => $record->user->getFullName()])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedNewspaper)
            ->emptyStateHeading(__('filament-resources.periodical_subscription.empty_heading'))
            ->emptyStateDescription(__('filament-resources.periodical_subscription.empty_description'));
    }

    /**
     * The copies of a publication, like a column of a spreadsheet, with their total at the bottom.
     */
    protected static function getPublicationColumn(Publication $publication): Column
    {
        return TextColumn::make("publication_{$publication->getKey()}")
            ->label($publication->getShortLabel())
            ->tooltip($publication->name)
            ->state(fn (PeriodicalSubscription $record): int => $record->quantityOf($publication))
            ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
            ->badge(fn (int $state): bool => $state > 0)
            ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
            ->alignCenter()
            ->summarize(Summarizer::make('total')
                ->label(__('filament-resources.periodical_subscription.summary_total'))
                ->using(fn (QueryBuilder $query): int => PeriodicalSubscription::totals($query->pluck('id'))[$publication->getKey()]['copies'] ?? 0));
    }

    /**
     * Sort the publishers by name, as in the rest of the application.
     *
     * @param  Builder<PeriodicalSubscription>  $query
     * @return Builder<PeriodicalSubscription>
     */
    protected static function sortByName(Builder $query, string $direction): Builder
    {
        return $query
            ->orderBy(User::query()->select('first_name')->whereColumn('users.id', 'periodical_subscriptions.user_id'), $direction)
            ->orderBy(User::query()->select('last_name')->whereColumn('users.id', 'periodical_subscriptions.user_id'), $direction);
    }

    public static function getDocumentation(): array
    {
        return [
            'publications.periodicals',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePeriodicalSubscriptions::route('/'),
        ];
    }
}
