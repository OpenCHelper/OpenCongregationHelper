<?php

namespace App\Filament\Resources\PeriodicalSubscriptions;

use App\Enums\NavigationGroup;
use App\Enums\Periodical;
use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Models\PeriodicalSubscription;
use App\Models\User;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Override;
use UnitEnum;

/**
 * The publishers receiving the periodicals on paper, with the copies of each one.
 */
class PeriodicalSubscriptionResource extends Resource
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
                Fieldset::make(__('filament-resources.periodical_subscription.section_quantities'))
                    ->columns(count(Periodical::cases()))
                    ->schema(array_map(
                        fn (Periodical $periodical): TextInput => TextInput::make("quantities.{$periodical->value}")
                            ->label($periodical->getLabel())
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(99)
                            ->default(0)
                            ->placeholder('0')
                            // An empty field means no copies
                            ->dehydrateStateUsing(fn (mixed $state): int => (int) $state)
                            ->rules($periodical === Periodical::cases()[0] ? [static::atLeastOneCopyRule()] : []),
                        Periodical::cases(),
                    )),
                Textarea::make('notes')
                    ->label(__('filament-resources.periodical_subscription.field_notes'))
                    ->placeholder(__('filament-resources.periodical_subscription.field_notes_placeholder'))
                    ->rows(2),
            ]);
    }

    /**
     * Require at least one copy of a periodical: a publisher receiving nothing should be removed from the list.
     */
    protected static function atLeastOneCopyRule(): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $copies = array_sum(array_map(
                fn (Periodical $periodical): int => (int) $get("quantities.{$periodical->value}"),
                Periodical::cases(),
            ));

            if ($copies < 1) {
                $fail(__('filament-resources.periodical_subscription.validation_at_least_one'));
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
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
                ...array_map(fn (Periodical $periodical): Column => static::getPeriodicalColumn($periodical), Periodical::cases()),
            ])
            ->filters([
                SelectFilter::make('periodical')
                    ->label(__('filament-resources.periodical_subscription.filter_periodical'))
                    ->options(Periodical::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        Periodical::tryFrom((string) ($data['value'] ?? '')),
                        fn (Builder $query, Periodical $periodical): Builder => $query->receiving($periodical),
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
     * The copies of a periodical, like a column of a spreadsheet, with their total at the bottom.
     */
    protected static function getPeriodicalColumn(Periodical $periodical): Column
    {
        return TextColumn::make("quantities.{$periodical->value}")
            ->label($periodical->getShortLabel())
            ->tooltip($periodical->getLabel())
            ->state(fn (PeriodicalSubscription $record): int => $record->quantityOf($periodical))
            ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
            ->badge(fn (int $state): bool => $state > 0)
            ->color(fn (int $state): string => $state > 0 ? $periodical->getColor() : 'gray')
            ->alignCenter()
            ->summarize(Summarizer::make('total')
                ->label(__('filament-resources.periodical_subscription.summary_total'))
                ->using(fn (QueryBuilder $query): int => PeriodicalSubscription::totals(
                    PeriodicalSubscription::query()->whereIn('id', $query->pluck('id'))->get(),
                )[$periodical->value]['copies']));
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

    public static function getPages(): array
    {
        return [
            'index' => ManagePeriodicalSubscriptions::route('/'),
        ];
    }
}
