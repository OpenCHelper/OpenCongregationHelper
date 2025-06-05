<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryAssignmentTypeResource\Pages;
use App\Filament\Resources\TerritoryAssignmentTypeResource\RelationManagers;
use App\Models\TerritoryAssignmentType;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class TerritoryAssignmentTypeResource extends Resource
{
    protected static ?string $model = TerritoryAssignmentType::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?int $navigationSort = 3;

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

    public static function getNavigationGroup(): ?string
    {
        return __('filament-resources.campaign.navigation_group');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('filament-resources.campaign.field_name'))
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label(__('filament-resources.campaign.field_description'))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('short_name')
                    ->label(__('filament-resources.campaign.field_short_name'))
                    ->maxLength(10),
                Forms\Components\ColorPicker::make('color')
                    ->label(__('filament-resources.campaign.field_color')),
                Forms\Components\Section::make(__('filament-resources.campaign.section_validity_dates'))
                    ->description(__('filament-resources.campaign.section_validity_dates_description'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label(__('filament-resources.campaign.field_valid_from'))
                            ->columnSpan(1)
                            ->format('Y-m-d H:i:s'),
                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label(__('filament-resources.campaign.field_valid_until'))
                            ->requiredWith('valid_from')
                            ->columnSpan(1)
                            ->format('Y-m-d H:i:s'),
                    ]),
                Forms\Components\Select::make('created_by')
                    ->label(__('filament-resources.campaign.field_created_by'))
                    ->disabled()
                    ->hiddenOn(['create', 'edit'])
                    ->default(Auth::id())
                    ->relationship(name: 'creator', titleAttribute: 'full_name')
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-resources.campaign.column_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('campaign_status')
                    ->label(__('filament-resources.campaign.column_campaign_status'))
                    ->state(function (TerritoryAssignmentType $record): string {
                        $valid_until = new Carbon($record->valid_until);
                        Log::info("Carbon: '" . $valid_until . "' - Difference: '" . $valid_until->diffInDays(now()) . "'");

                        $status = match (true) {
                            $valid_until->diffInDays(now()) >= 0 => __('filament-resources.campaign.status_terminated'),
                            $valid_until->diffInDays(now()) >= -3 => __('filament-resources.campaign.status_almost_expired'),
                            $valid_until->diffInDays(now()) >= -7 => __('filament-resources.campaign.status_is_expiring'),
                            default => __('filament-resources.campaign.status_active'),
                        };
                        return $status;
                    })
                    ->badge()
                    ->sortable(['valid_until'])
                    ->color(fn (string $state): string => match ($state) {
                        __('filament-resources.campaign.status_active') => 'success',
                        __('filament-resources.campaign.status_is_expiring') => 'warning',
                        __('filament-resources.campaign.status_almost_expired') => 'danger',
                        __('filament-resources.campaign.status_terminated') => 'gray',
                    }),
                Tables\Columns\TextColumn::make('short_name')
                    ->label(__('filament-resources.campaign.column_short_name'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.full_name')
                    ->label(__('filament-resources.campaign.column_creator'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('filament-resources.campaign.column_description'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label(__('filament-resources.campaign.column_valid_from'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('filament-resources.campaign.column_valid_until'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-resources.campaign.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-resources.campaign.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('filament-resources.campaign.column_deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\RestoreBulkAction::make()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTerritoryAssignmentTypes::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
