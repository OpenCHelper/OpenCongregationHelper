<?php

namespace App\Filament\Resources\PublicationSubscribers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\PublicationSubscribers\Pages\ManagePublicationSubscribers;
use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\Publications\PublicationResource;
use App\Models\PublicationSubscriber;
use App\Models\Publication;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
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
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Override;
use UnitEnum;

/**
 * The publishers receiving publications on paper regularly, with the copies of each one: their active subscriptions,
 * each one requested again every period (see `PublicationSubscription::renew()`).
 *
 * The publications are the renewable ones offered in their list (`PublicationResource`): a column each. Removing a
 * publication or a publisher stops the subscriptions instead of deleting them, so that their history is kept.
 */
class PublicationSubscriberResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = PublicationSubscriber::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Newspaper;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('filament-resources.publication_subscriber.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.publication_subscriber.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-resources.publication_subscriber.navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Publications;
    }

    public static function form(Schema $schema): Schema
    {
        $publications = Publication::offeredRenewable();

        return $schema
            ->columns(1)
            ->components([
                Select::make('user_id')
                    ->label(__('filament-resources.publication_subscriber.field_user'))
                    ->relationship(
                        'user',
                        'first_name',
                        // A publisher is listed once: the ones already in the list can't be chosen again
                        modifyQueryUsing: fn (Builder $query, ?PublicationSubscriber $record): Builder => $query
                            ->whereDoesntHave('publicationSubscriber', fn (Builder $query): Builder => $query->active()->whereKeyNot($record?->getKey() ?? 0))
                            ->orderBy('first_name')
                            ->orderBy('last_name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->getFullName())
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->required()
                    // Listed once; a publisher removed from the list can be added again (with their history)
                    ->rule(fn (?PublicationSubscriber $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $alreadyListed = PublicationSubscriber::query()
                            ->active()
                            ->where('user_id', $value)
                            ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($alreadyListed) {
                            $fail(__('filament-resources.publication_subscriber.validation_already_listed'));
                        }
                    }),
                // One row for each publication received, so that the form stays short however many are offered
                Repeater::make('activeSubscriptions')
                    ->label(__('filament-resources.publication_subscriber.field_items'))
                    ->relationship()
                    // Saved as the history of the subscriptions: started, changed or stopped, never deleted
                    ->saveRelationshipsUsing(fn (PublicationSubscriber $record, ?array $state) => $record->syncSubscriptions(array_values($state ?? [])))
                    ->table([
                        TableColumn::make(__('filament-resources.publication_subscriber.field_publication'))->markAsRequired(),
                        TableColumn::make(__('filament-resources.publication_subscriber.field_quantity'))->markAsRequired(),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('publication_id')
                            ->label(__('filament-resources.publication_subscriber.field_publication'))
                            ->options(fn (?int $state): array => static::getPublicationOptions($state))
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->distinct()
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label(__('filament-resources.publication_subscriber.field_quantity'))
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
                        'min' => __('filament-resources.publication_subscriber.validation_at_least_one'),
                    ])
                    ->addActionLabel(__('filament-resources.publication_subscriber.action_add_publication'))
                    ->helperText(fn (): ?string => $publications->isEmpty()
                        ? __('filament-resources.publication_subscriber.no_publications', ['list' => PublicationResource::getNavigationLabel()])
                        : null),
                Textarea::make('notes')
                    ->label(__('filament-resources.publication_subscriber.field_notes'))
                    ->placeholder(__('filament-resources.publication_subscriber.field_notes_placeholder'))
                    ->rows(2),
            ]);
    }

    /**
     * The publications that can be chosen, grouped by category: the renewable offered ones, plus the one already chosen
     * in the row, even if no longer offered (so that its copies are kept and shown).
     *
     * @return array<string, array<int, string>>
     */
    public static function getPublicationOptions(?int $selected = null): array
    {
        return Publication::query()
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query->where('is_active', true)->where('renewal_months', '>', 0))
                ->when($selected, fn (Builder $query): Builder => $query->orWhere('id', $selected)))
            ->ordered()
            ->get()
            ->groupBy(fn (Publication $publication): string => $publication->category->getLabel())
            ->map(fn (Collection $publications): array => $publications
                ->mapWithKeys(fn (Publication $publication): array => [$publication->getKey() => $publication->is_active && $publication->isRenewable()
                    ? $publication->name
                    : __('filament-resources.publication_subscriber.publication_not_offered', ['name' => $publication->name])])
                ->all())
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->active()->with(['user', 'activeSubscriptions']))
            ->defaultSort(fn (Builder $query): Builder => static::sortByName($query, 'asc'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('user.full_name')
                    ->label(__('filament-resources.publication_subscriber.column_user'))
                    ->icon(Heroicon::OutlinedUser)
                    ->weight(FontWeight::Medium)
                    ->description(fn (PublicationSubscriber $record): ?string => $record->notes)
                    ->wrap()
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => static::sortByName($query, $direction)),
                // The publications of each category under a common header
                ...Publication::offeredRenewable()
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
                    ->label(__('filament-resources.publication_subscriber.filter_publication'))
                    // Grouped by category
                    ->options(fn (): array => Publication::offeredRenewable()
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
                    ->modalHeading(fn (PublicationSubscriber $record): string => $record->user->getFullName()),
                Action::make('requests')
                    ->label(__('filament-resources.publication_subscriber.action_requests'))
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->url(fn (PublicationSubscriber $record): string => PublicationRequestResource::getFilteredUrl(user: $record->user_id))
                    ->visible(fn (): bool => PublicationRequestResource::canViewAny()),
                // Stops the subscriptions instead of deleting them, to keep the history
                Action::make('remove')
                    ->label(__('filament-resources.publication_subscriber.action_remove'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (PublicationSubscriber $record): string => __('filament-resources.publication_subscriber.action_remove_heading', ['name' => $record->user->getFullName()]))
                    ->modalDescription(__('filament-resources.publication_subscriber.action_remove_description'))
                    ->authorize(fn (PublicationSubscriber $record): bool => Gate::allows('update', $record))
                    ->action(fn (PublicationSubscriber $record) => $record->stopSubscriptions())
                    ->successNotificationTitle(__('filament-resources.publication_subscriber.notification_removed')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('remove')
                        ->label(__('filament-resources.publication_subscriber.action_remove'))
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('filament-resources.publication_subscriber.action_remove_description'))
                        ->authorize(fn (): bool => Gate::allows('update', new PublicationSubscriber))
                        ->action(fn (EloquentCollection $records) => $records->each(fn (PublicationSubscriber $record) => $record->stopSubscriptions()))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(__('filament-resources.publication_subscriber.notification_removed')),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedNewspaper)
            ->emptyStateHeading(__('filament-resources.publication_subscriber.empty_heading'))
            ->emptyStateDescription(__('filament-resources.publication_subscriber.empty_description'));
    }

    /**
     * The copies of a publication, like a column of a spreadsheet, with their total at the bottom.
     */
    protected static function getPublicationColumn(Publication $publication): Column
    {
        return TextColumn::make("publication_{$publication->getKey()}")
            ->label($publication->getShortLabel())
            ->tooltip($publication->name)
            ->state(fn (PublicationSubscriber $record): int => $record->quantityOf($publication))
            ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
            ->badge(fn (int $state): bool => $state > 0)
            ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
            ->alignCenter()
            ->summarize(Summarizer::make('total')
                ->label(__('filament-resources.publication_subscriber.summary_total'))
                ->using(fn (QueryBuilder $query): int => PublicationSubscriber::totals($query->pluck('id'))[$publication->getKey()]['copies'] ?? 0));
    }

    /**
     * Sort the publishers by name, as in the rest of the application.
     *
     * @param  Builder<PublicationSubscriber>  $query
     * @return Builder<PublicationSubscriber>
     */
    protected static function sortByName(Builder $query, string $direction): Builder
    {
        return $query
            ->orderBy(User::query()->select('first_name')->whereColumn('users.id', 'publication_subscribers.user_id'), $direction)
            ->orderBy(User::query()->select('last_name')->whereColumn('users.id', 'publication_subscribers.user_id'), $direction);
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
            'index' => ManagePublicationSubscribers::route('/'),
        ];
    }
}
