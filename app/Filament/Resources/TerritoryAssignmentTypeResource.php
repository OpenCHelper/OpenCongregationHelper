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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TerritoryAssignmentTypeResource extends Resource
{
    protected static ?string $model = TerritoryAssignmentType::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'campagna';
    }

    public static function getPluralModelLabel(): string
    {
        return 'campagne';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reparto territori';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('short_name')
                    ->maxLength(10),
                Forms\Components\ColorPicker::make('color'),
                Forms\Components\Section::make('Validity dates')
                    ->description('Set the date range for when this assignment type is valid (default is always)')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->columnSpan(1)
                            ->format('Y-m-d H:i:s'),
                        Forms\Components\DateTimePicker::make('valid_until')
                            ->requiredWith('valid_from')
                            ->columnSpan(1)
                            ->format('Y-m-d H:i:s'),
                    ]),
                Forms\Components\Select::make('created_by')
                    ->disabled()
                    ->hiddenOn(['create', 'edit'])
                    ->default(Auth::id())
                    ->relationship(name: 'creator', titleAttribute: 'name')
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('campaign_status')
                    ->state(function (TerritoryAssignmentType $record): string {
                        $valid_until = new Carbon($record->valid_until);
                        Log::info("Carbon: '" . $valid_until . "' - Difference: '" . $valid_until->diffInDays(now()) . "'");

                        $status = match (true) {
                            $valid_until->diffInDays(now()) >= 0 => 'terminated',
                            $valid_until->diffInDays(now()) >= -3 => 'almost_expired',
                            $valid_until->diffInDays(now()) >= -7 => 'is_expiring',
                            default => 'active',
                        };
                        return $status;
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'is_expiring' => 'warning',
                        'almost_expired' => 'danger',
                        'terminated' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('short_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('valid_from')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('valid_until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
