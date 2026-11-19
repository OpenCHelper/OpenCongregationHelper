<?php

namespace App\Filament\Resources\PublicationRequests;

use App\Enums\NavigationGroup;
use App\Enums\PublicationRequestStatus;
use App\Filament\Resources\PublicationRequests\Pages\ManagePublicationRequests;
use App\Filament\Resources\Users\Pages\ManageUserPublicationRequests;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\PublicationSubscriber;
use App\Models\PublicationSubscription;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Override;
use UnitEnum;

/**
 * The requests of the publications: made once (e.g. a book) or again every period by a subscription (e.g. The
 * Watchtower), and followed until they are delivered. Ordering them from the branch is optional.
 */
class PublicationRequestResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = PublicationRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::InboxStack;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('filament-resources.publication_request.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.publication_request.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Publications;
    }

    /**
     * The requests still to order or to retrieve, as a reminder in the menu.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = PublicationRequest::query()->toOrder()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * Get the URL of the history of the requests of a publisher or of a publication.
     */
    public static function getFilteredUrl(?int $user = null, ?int $publication = null): string
    {
        return static::getUrl('index', [
            'tab' => 'all',
            'filters' => array_filter([
                'user' => $user ? ['value' => $user] : null,
                'publication' => $publication ? ['value' => $publication] : null,
            ]),
        ]);
    }

    /**
     * The publications that can be requested, grouped by category: the offered ones, plus the one already chosen.
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
            ->map(fn (Collection $publications): array => $publications->pluck('name', 'id')->all())
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('user_id')
                    ->label(__('filament-resources.publication_request.field_user'))
                    ->relationship('user', 'first_name', fn (Builder $query): Builder => $query->orderBy('first_name')->orderBy('last_name'))
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->getFullName())
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->required()
                    // Not moved to another publisher: a renewal belongs to the publisher of its subscription, and a
                    // request edited from the history of a publisher stays in it
                    ->disabled(fn (string $operation, Component $livewire, ?PublicationRequest $record): bool => static::isPublisherLocked($operation, $livewire, $record))
                    ->dehydrated(fn (string $operation, Component $livewire, ?PublicationRequest $record): bool => ! static::isPublisherLocked($operation, $livewire, $record)),
                Grid::make(['default' => 1, 'sm' => 3])
                    ->schema([
                        Select::make('publication_id')
                            ->label(__('filament-resources.publication_request.field_publication'))
                            ->options(fn (?int $state): array => static::getPublicationOptions($state))
                            ->searchable()
                            ->required()
                            ->live()
                            // A publication received regularly is changed in the regular publications, not requested again
                            ->rule(fn (string $operation, Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($operation, $get): void {
                                if (static::isRenewingFromTheForm($operation, $get) && PublicationSubscription::query()
                                    ->active()
                                    ->where('publication_id', $value)
                                    ->whereHas('subscriber', fn (Builder $query): Builder => $query->where('user_id', $get('user_id')))
                                    ->exists()) {
                                    $fail(__('filament-resources.publication_request.validation_already_received'));
                                }
                            })
                            ->columnSpan(['sm' => 2]),
                        TextInput::make('quantity')
                            ->label(__('filament-resources.publication_request.field_quantity'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(999)
                            ->default(1)
                            ->required(),
                    ]),
                // Only when creating: a renewable publication (e.g. The Watchtower) can be requested every period
                Toggle::make('renews')
                    ->label(fn (Get $get): string => __('filament-resources.publication_request.field_renews', [
                        'every' => trans_choice('filament-resources.publication.renewal_every', (int) Publication::find($get('publication_id'))?->renewal_months, [
                            'count' => (int) Publication::find($get('publication_id'))?->renewal_months,
                        ]),
                    ]))
                    ->helperText(__('filament-resources.publication_request.field_renews_helper'))
                    ->default(true)
                    ->live()
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (bool) Publication::find($get('publication_id'))?->isRenewable()),
                Grid::make(['default' => 1, 'sm' => 3])
                    ->schema([
                        DatePicker::make('requested_at')
                            ->label(__('filament-resources.publication_request.field_requested_at'))
                            ->default(now())
                            ->maxDate(now())
                            // Requested again from this period: an earlier date would request it again for every
                            // period since then, all at once
                            ->minDate(fn (string $operation, Get $get): ?Carbon => static::isRenewingFromTheForm($operation, $get) ? today()->startOfMonth() : null)
                            ->helperText(fn (string $operation, Get $get): ?string => static::isRenewingFromTheForm($operation, $get)
                                ? __('filament-resources.publication_request.field_requested_at_renews_helper')
                                : null)
                            ->required(),
                        DatePicker::make('ordered_at')
                            ->label(__('filament-resources.publication_request.field_ordered_at'))
                            ->helperText(__('filament-resources.publication_request.field_ordered_at_helper'))
                            ->afterOrEqual('requested_at')
                            ->hidden(fn (string $operation, Get $get): bool => static::isRenewingFromTheForm($operation, $get)),
                        DatePicker::make('delivered_at')
                            ->label(__('filament-resources.publication_request.field_delivered_at'))
                            ->afterOrEqual('requested_at')
                            ->rule(fn (Get $get): string => filled($get('ordered_at')) ? 'after_or_equal:'.$get('ordered_at') : 'nullable')
                            ->hidden(fn (string $operation, Get $get): bool => static::isRenewingFromTheForm($operation, $get)),
                    ]),
                Textarea::make('notes')
                    ->label(__('filament-resources.publication_request.field_notes'))
                    ->rows(2),
            ]);
    }

    /**
     * Whether the publisher of the request can't be changed: the ones renewed by a subscription, and the requests
     * edited from the history of a publisher.
     */
    public static function isPublisherLocked(string $operation, Component $livewire, ?PublicationRequest $record): bool
    {
        return $operation === 'edit' && ($livewire instanceof ManageUserPublicationRequests || (bool) $record?->isRenewal());
    }

    /**
     * Whether the form creates a subscription (renewed every period) rather than a single request.
     */
    public static function isRenewingFromTheForm(string $operation, Get $get): bool
    {
        return $operation === 'create'
            && (bool) $get('renews')
            && (bool) Publication::find($get('publication_id'))?->isRenewable();
    }

    /**
     * Save a new request from the form: a renewable one starts a subscription of the publisher (shown in the paper
     * publications), which makes this request and then one every period.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createFromForm(array $data): PublicationRequest
    {
        $publication = Publication::findOrFail($data['publication_id']);
        $renews = (bool) ($data['renews'] ?? false) && $publication->isRenewable();
        unset($data['renews']);

        if (! $renews) {
            return PublicationRequest::query()->create($data);
        }

        // All or nothing: the publisher in the regular publications, their subscription and its first request
        return DB::transaction(function () use ($data, $publication): PublicationRequest {
            $subscriber = PublicationSubscriber::query()->firstOrCreate(['user_id' => $data['user_id']]);

            // Rejected by the form when already received: changed in the regular publications instead
            $made = $subscriber->activeSubscriptions()->where('publication_id', $publication->getKey())->exists()
                ? []
                : $subscriber->startSubscription($publication->getKey(), (int) $data['quantity'], $data['requested_at'])->requests()->get()->all();

            // The request just made or, when its period was already requested (e.g. received again in the same month),
            // the last one of the publisher
            $request = $made[0] ?? PublicationRequest::query()
                ->where('user_id', $data['user_id'])
                ->where('publication_id', $publication->getKey())
                ->latest('requested_at')
                ->latest('id')
                ->firstOrFail();

            if ($made !== [] && filled($data['notes'] ?? null)) {
                $request->update(['notes' => $data['notes']]);
            }

            return $request;
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'publication']))
            ->defaultSort('requested_at', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->groups([
                // With the total of the copies: the requests to order, by publication, are the order for the branch.
                // On the columns of the requests, not of their relationships: Filament would sum them with a raw
                // column name, without the table prefix of the installation (e.g. on the previews).
                Group::make('publication_id')
                    ->label(__('filament-resources.publication_request.group_publication'))
                    ->getTitleFromRecordUsing(fn (PublicationRequest $record): string => $record->publication->name)
                    ->collapsible(),
                Group::make('user_id')
                    ->label(__('filament-resources.publication_request.group_user'))
                    ->getTitleFromRecordUsing(fn (PublicationRequest $record): string => $record->user->getFullName())
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('requested_at')
                    ->label(__('filament-resources.publication_request.column_requested_at'))
                    // As the dates of the other steps
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('user.full_name')
                    ->label(__('filament-resources.publication_request.column_user'))
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable(['first_name', 'last_name'])
                    ->wrap(),
                TextColumn::make('publication.name')
                    ->label(__('filament-resources.publication_request.column_publication'))
                    ->weight(FontWeight::Medium)
                    ->description(fn (PublicationRequest $record): string => $record->publication->category->getLabel())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label(__('filament-resources.publication_request.column_quantity'))
                    ->alignCenter()
                    ->summarize(Sum::make('total')->label(__('filament-resources.publication_request.summary_total'))),
                TextColumn::make('status')
                    ->label(__('filament-resources.publication_request.column_status'))
                    ->state(fn (PublicationRequest $record): PublicationRequestStatus => $record->getStatus())
                    ->badge()
                    ->description(fn (PublicationRequest $record): ?string => match ($record->getStatus()) {
                        PublicationRequestStatus::Delivered => $record->delivered_at->translatedFormat('j M Y'),
                        PublicationRequestStatus::Ordered => $record->ordered_at->translatedFormat('j M Y'),
                        default => null,
                    }),
                IconColumn::make('renewal')
                    ->label(__('filament-resources.publication_request.column_renewal'))
                    ->state(fn (PublicationRequest $record): bool => $record->isRenewal())
                    ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::OutlinedArrowPath : null)
                    ->tooltip(fn (bool $state): ?string => $state ? __('filament-resources.publication_request.renewal_tooltip') : null)
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('notes')
                    ->label(__('filament-resources.publication_request.column_notes'))
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('creator.full_name')
                    ->label(__('filament-resources.publication_request.column_creator'))
                    ->placeholder(__('filament-resources.publication_request.creator_scheduler'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('publication')
                    ->label(__('filament-resources.publication_request.filter_publication'))
                    ->options(fn (): array => static::getPublicationOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $publicationId): Builder => $query->where('publication_id', $publicationId),
                    ))
                    ->searchable(),
                SelectFilter::make('user')
                    ->label(__('filament-resources.publication_request.filter_user'))
                    ->relationship('user', 'first_name', fn (Builder $query): Builder => $query->orderBy('first_name')->orderBy('last_name'))
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->getFullName())
                    ->searchable(['first_name', 'last_name'])
                    ->preload(),
                Filter::make('requested_between')
                    ->schema([
                        DatePicker::make('from')->label(__('filament-resources.publication_request.filter_requested_from')),
                        DatePicker::make('until')->label(__('filament-resources.publication_request.filter_requested_until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('requested_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('requested_at', '<=', $until))),
                TernaryFilter::make('renewal')
                    ->label(__('filament-resources.publication_request.filter_renewal'))
                    ->trueLabel(__('filament-resources.publication_request.filter_renewal_true'))
                    ->falseLabel(__('filament-resources.publication_request.filter_renewal_false'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('subscription_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('subscription_id'),
                    ),
            ])
            ->recordActions([
                static::markAsOrderedAction(),
                static::markAsDeliveredAction(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::markAsOrderedBulkAction(),
                    static::markAsDeliveredBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedInboxStack)
            ->emptyStateHeading(__('filament-resources.publication_request.empty_heading'))
            ->emptyStateDescription(__('filament-resources.publication_request.empty_description'));
    }

    /**
     * The date of a step of the request, today by default.
     */
    protected static function getStepDateField(string $label): DatePicker
    {
        return DatePicker::make('date')
            ->label($label)
            ->default(now())
            ->maxDate(now())
            ->required();
    }

    public static function markAsOrderedAction(): Action
    {
        return Action::make('markAsOrdered')
            ->label(__('filament-resources.publication_request.action_mark_ordered'))
            ->icon(PublicationRequestStatus::Ordered->getIcon())
            ->color(PublicationRequestStatus::Ordered->getColor())
            ->visible(fn (PublicationRequest $record): bool => $record->getStatus() === PublicationRequestStatus::Requested)
            ->authorize(fn (PublicationRequest $record): bool => Gate::allows('update', $record))
            ->schema([static::getStepDateField(__('filament-resources.publication_request.field_ordered_at'))])
            ->modalWidth('sm')
            ->action(fn (PublicationRequest $record, array $data) => $record->update(['ordered_at' => max($data['date'], $record->requested_at->toDateString())]))
            ->successNotificationTitle(__('filament-resources.publication_request.notification_ordered'));
    }

    /**
     * Also available straight from the requested step: the copies may already be in storage.
     */
    public static function markAsDeliveredAction(): Action
    {
        return Action::make('markAsDelivered')
            ->label(__('filament-resources.publication_request.action_mark_delivered'))
            ->icon(PublicationRequestStatus::Delivered->getIcon())
            ->color(PublicationRequestStatus::Delivered->getColor())
            ->visible(fn (PublicationRequest $record): bool => $record->getStatus() !== PublicationRequestStatus::Delivered)
            ->authorize(fn (PublicationRequest $record): bool => Gate::allows('update', $record))
            ->schema([static::getStepDateField(__('filament-resources.publication_request.field_delivered_at'))])
            ->modalWidth('sm')
            ->action(fn (PublicationRequest $record, array $data) => $record->update(['delivered_at' => static::notBeforePreviousSteps($record, $data['date'])]))
            ->successNotificationTitle(__('filament-resources.publication_request.notification_delivered'));
    }

    public static function markAsOrderedBulkAction(): BulkAction
    {
        return BulkAction::make('markAsOrdered')
            ->label(__('filament-resources.publication_request.action_mark_ordered'))
            ->icon(PublicationRequestStatus::Ordered->getIcon())
            ->color(PublicationRequestStatus::Ordered->getColor())
            ->authorize(fn (): bool => Gate::allows('update', new PublicationRequest))
            ->schema([static::getStepDateField(__('filament-resources.publication_request.field_ordered_at'))])
            ->modalWidth('sm')
            ->action(fn (EloquentCollection $records, array $data) => $records
                ->filter(fn (PublicationRequest $record): bool => $record->getStatus() === PublicationRequestStatus::Requested)
                ->each(fn (PublicationRequest $record) => $record->update(['ordered_at' => max($data['date'], $record->requested_at->toDateString())])))
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(__('filament-resources.publication_request.notification_ordered'));
    }

    public static function markAsDeliveredBulkAction(): BulkAction
    {
        return BulkAction::make('markAsDelivered')
            ->label(__('filament-resources.publication_request.action_mark_delivered'))
            ->icon(PublicationRequestStatus::Delivered->getIcon())
            ->color(PublicationRequestStatus::Delivered->getColor())
            ->authorize(fn (): bool => Gate::allows('update', new PublicationRequest))
            ->schema([static::getStepDateField(__('filament-resources.publication_request.field_delivered_at'))])
            ->modalWidth('sm')
            ->action(fn (EloquentCollection $records, array $data) => $records
                ->filter(fn (PublicationRequest $record): bool => $record->getStatus() !== PublicationRequestStatus::Delivered)
                ->each(fn (PublicationRequest $record) => $record->update(['delivered_at' => static::notBeforePreviousSteps($record, $data['date'])])))
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(__('filament-resources.publication_request.notification_delivered'));
    }

    /**
     * Keep the steps in order, e.g. when many requests made on different days are delivered together.
     */
    protected static function notBeforePreviousSteps(PublicationRequest $record, string $date): string
    {
        return max($date, $record->requested_at->toDateString(), (string) $record->ordered_at?->toDateString());
    }

    public static function getDocumentation(): array
    {
        return [
            'publications.requests',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePublicationRequests::route('/'),
        ];
    }
}
