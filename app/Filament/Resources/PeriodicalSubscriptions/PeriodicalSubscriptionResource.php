<?php

namespace App\Filament\Resources\PeriodicalSubscriptions;

use App\Enums\NavigationGroup;
use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Filament\Resources\Publications\PublicationResource;
use App\Models\PeriodicalSubscription;
use App\Models\Publication;
use App\Models\User;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
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
use Illuminate\Database\Eloquent\Model;
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
        $firstPublication = $publications->first();

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
                ...($publications->isEmpty()
                    ? [
                        TextEntry::make('no_publications')
                            ->hiddenLabel()
                            ->state(__('filament-resources.periodical_subscription.no_publications', ['list' => PublicationResource::getNavigationLabel()])),
                    ]
                    // A box of fields for each category, e.g. the periodicals apart from the books
                    : $publications
                        ->groupBy(fn (Publication $publication): string => $publication->category->value)
                        ->map(fn (Collection $category): Fieldset => Fieldset::make($category->first()->category->getLabel())
                            ->columns(['default' => 1, 'sm' => min(3, $category->count())])
                            ->schema($category->map(fn (Publication $publication): TextInput => static::getQuantityField(
                                $publication,
                                isFirst: $publication->is($firstPublication),
                            ))->all()))
                        ->values()
                        ->all()),
                Textarea::make('notes')
                    ->label(__('filament-resources.periodical_subscription.field_notes'))
                    ->placeholder(__('filament-resources.periodical_subscription.field_notes_placeholder'))
                    ->rows(2),
            ]);
    }

    /**
     * The field with the copies of a publication: the first one also checks that at least a copy is received.
     */
    protected static function getQuantityField(Publication $publication, bool $isFirst): TextInput
    {
        return TextInput::make("quantities.{$publication->getFormKey()}")
            ->label($publication->name)
            ->numeric()
            ->integer()
            ->minValue(0)
            ->maxValue(99)
            ->default(0)
            ->placeholder('0')
            // An empty field means no copies
            ->dehydrateStateUsing(fn (mixed $state): int => (int) $state)
            ->rules($isFirst ? [static::atLeastOneCopyRule()] : []);
    }

    /**
     * Require at least one copy of a publication: a publisher receiving nothing should be removed from the list.
     */
    protected static function atLeastOneCopyRule(): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $copies = array_sum(array_map(intval(...), (array) $get('quantities')));

            if ($copies < 1) {
                $fail(__('filament-resources.periodical_subscription.validation_at_least_one'));
            }
        };
    }

    /**
     * Save the form of a publisher, with the copies of each publication.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveRecord(?PeriodicalSubscription $record, array $data): Model
    {
        return ($record ?? new PeriodicalSubscription)->saveWithQuantities($data);
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
                    ->modalHeading(fn (PeriodicalSubscription $record): string => $record->user->getFullName())
                    ->mutateRecordDataUsing(fn (array $data, PeriodicalSubscription $record): array => [
                        ...$data,
                        'quantities' => [
                            ...Publication::offered()->mapWithKeys(fn (Publication $publication): array => [$publication->getFormKey() => 0])->all(),
                            ...$record->getFormQuantities(),
                        ],
                    ])
                    ->using(fn (PeriodicalSubscription $record, array $data): Model => static::saveRecord($record, $data)),
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
