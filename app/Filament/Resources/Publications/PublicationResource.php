<?php

namespace App\Filament\Resources\Publications;

use App\Enums\NavigationGroup;
use App\Enums\PublicationCategory;
use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Models\Publication;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

/**
 * The publications that the publishers can receive on paper, managed by the publications department.
 */
class PublicationResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Publication::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::BookOpen;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('filament-resources.publication.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.publication.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-resources.publication.navigation_label');
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
                TextInput::make('name')
                    ->label(__('filament-resources.publication.field_name'))
                    ->placeholder(__('filament-resources.publication.field_name_placeholder'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('category')
                    ->label(__('filament-resources.publication.field_category'))
                    ->options(PublicationCategory::class)
                    ->default(PublicationCategory::Books)
                    ->required()
                    ->native(false),
                TextInput::make('short_name')
                    ->label(__('filament-resources.publication.field_short_name'))
                    ->placeholder(__('filament-resources.publication.field_short_name_placeholder'))
                    ->helperText(__('filament-resources.publication.field_short_name_helper'))
                    ->maxLength(50),
                TextInput::make('renewal_months')
                    ->label(__('filament-resources.publication.field_renewal_months'))
                    ->helperText(__('filament-resources.publication.field_renewal_months_helper'))
                    ->suffix(__('filament-resources.publication.field_renewal_months_suffix'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(24)
                    // Empty: requested once (e.g. a book)
                    ->nullable(),
                Toggle::make('is_active')
                    ->label(__('filament-resources.publication.field_is_active'))
                    ->helperText(__('filament-resources.publication.field_is_active_helper'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['activeSubscriptions' => fn (Builder $query): Builder => $query->where('quantity', '>', 0)])
                ->withSum('activeSubscriptions', 'quantity')
                ->withCount(['requests as pending_requests_count' => fn (Builder $query): Builder => $query->whereNull('delivered_at')]))
            ->defaultSort(fn (Builder $query): Builder => $query->ordered())
            ->reorderable('sort')
            ->defaultGroup(Group::make('category')
                ->label(__('filament-resources.publication.field_category'))
                ->getTitleFromRecordUsing(fn (Publication $record): string => $record->category->getLabel())
                // Groups in the order of the categories, not in alphabetical order
                ->orderQueryUsing(fn (Builder $query): Builder => $query)
                ->titlePrefixedWithLabel(false))
            ->groupingSettingsHidden()
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.publication.column_name'))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->weight(FontWeight::Medium)
                    ->description(fn (Publication $record): ?string => $record->short_name)
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label(__('filament-resources.publication.column_is_active'))
                    ->alignCenter(),
                TextColumn::make('renewal_months')
                    ->label(__('filament-resources.publication.column_renewal'))
                    ->formatStateUsing(fn (?int $state): string => trans_choice('filament-resources.publication.renewal_every', (int) $state, ['count' => $state]))
                    ->placeholder(__('filament-resources.publication.renewal_none'))
                    ->icon(fn (?int $state): ?Heroicon => $state ? Heroicon::OutlinedArrowPath : null)
                    ->alignCenter(),
                TextColumn::make('active_subscriptions_count')
                    ->label(__('filament-resources.publication.column_publishers'))
                    ->alignCenter(),
                TextColumn::make('active_subscriptions_sum_quantity')
                    ->label(__('filament-resources.publication.column_copies'))
                    ->default(0)
                    ->alignCenter(),
                TextColumn::make('pending_requests_count')
                    ->label(__('filament-resources.publication.column_pending_requests'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('filament-resources.publication.field_category'))
                    ->options(PublicationCategory::class)
                    ->multiple(),
            ])
            ->recordActions([
                Action::make('requests')
                    ->label(__('filament-resources.publication.action_requests'))
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->url(fn (Publication $record): string => PublicationRequestResource::getFilteredUrl(publication: $record->getKey()))
                    ->visible(fn (): bool => PublicationRequestResource::canViewAny()),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription(__('filament-resources.publication.delete_description')),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBookOpen)
            ->emptyStateHeading(__('filament-resources.publication.empty_heading'))
            ->emptyStateDescription(__('filament-resources.publication.empty_description'));
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
            'index' => ManagePublications::route('/'),
        ];
    }
}
