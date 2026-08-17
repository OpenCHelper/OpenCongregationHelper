<?php

namespace App\Filament\Resources\Campaigns;

use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Resources\Campaigns\Pages\ManageCampaignAssignments;
use App\Enums\CampaignStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::EnvelopeOpen;
    protected static ?int $navigationSort = 3;
    protected static ?\Filament\Pages\Enums\SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->name . ($record->short_name ? ' (' . $record->short_name . ')' : '');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            ...($record->description ? [__('filament-resources.global_search_results.description') => $record->description] : []),
            ...($record->valid_until ? [__('filament-resources.global_search_results.validity') => $record->valid_from . ' - ' . $record->valid_until] : []),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('filament-resources.campaign.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.campaign.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Territories;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament-resources.campaign.field_name'))
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label(__('filament-resources.campaign.field_description'))
                    ->columnSpanFull(),
                TextInput::make('short_name')
                    ->label(__('filament-resources.campaign.field_short_name'))
                    ->maxLength(10),
                ColorPicker::make('color')
                    ->label(__('filament-resources.campaign.field_color')),
                Section::make(__('filament-resources.campaign.section_validity_dates'))
                    ->description(__('filament-resources.campaign.section_validity_dates_description'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('valid_from')
                            ->label(__('filament-resources.campaign.field_valid_from'))
                            ->columnSpan(1)
                            ->format('Y-m-d H:i:s')
                            ->requiredWith('valid_until'),
                        DateTimePicker::make('valid_until')
                            ->label(__('filament-resources.campaign.field_valid_until'))
                            ->requiredWith('valid_from')
                            ->columnSpan(1)
                            ->format('Y-m-d H:i:s')
                            ->after('valid_from'),
                    ]),
                Select::make('created_by')
                    ->label(__('filament-resources.campaign.field_created_by'))
                    ->disabled()
                    ->hiddenOn(['create'])
                    ->default(Auth::id())
                    ->relationship(
                        name: 'creator',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}")
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.campaign.column_name'))
                    ->searchable(),
                TextColumn::make('campaign_status')
                    ->label(__('filament-resources.campaign.column_campaign_status'))
                    ->state(fn (Campaign $record): string => CampaignStatus::fromCampaign($record)->getLabel())
                    ->badge()
                    ->sortable(['valid_until'])
                    ->color(fn (Campaign $record): string => CampaignStatus::fromCampaign($record)->getColor()),
                TextColumn::make('short_name')
                    ->label(__('filament-resources.campaign.column_short_name'))
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label(__('filament-resources.campaign.column_creator'))
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('filament-resources.campaign.column_description'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_from')
                    ->label(__('filament-resources.campaign.column_valid_from'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_until')
                    ->label(__('filament-resources.campaign.column_valid_until'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('filament-resources.campaign.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-resources.campaign.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('filament-resources.campaign.column_deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label(__('filament-resources.campaign.filter_trashed')),
                // Tables\Filters\TernaryFilter::make('valid_until')
                //     ->default(false)
                //     ->nullable()
                //     ->boolean()
                //     ->label('Stato campagna')
                //     ->placeholder('Qualsiasi')
                //     ->trueLabel('Terminata')
                //     ->falseLabel('In corso'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                    ForceDeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                    RestoreBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'view' => ViewCampaign::route('/{record}'),
            'assignments' => ManageCampaignAssignments::route('/{record}/assignments'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewCampaign::class,
            ManageCampaignAssignments::class,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
