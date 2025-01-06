<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryZoneResource\Pages;
use App\Filament\Resources\TerritoryZoneResource\RelationManagers;
use App\Models\TerritoryZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class TerritoryZoneResource extends Resource
{
    protected static ?string $model = TerritoryZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nome_zona')
                    ->required()
                    ->maxLength(50),
                Forms\Components\ColorPicker::make('color'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_zona')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('territories_count')
                    ->counts('territories')
                    ->label('Totale territori')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTerritoryZones::route('/'),
            'create' => Pages\CreateTerritoryZone::route('/create'),
            'view' => Pages\ViewTerritoryZone::route('/{record}'),
            'edit' => Pages\EditTerritoryZone::route('/{record}/edit'),
        ];
    }
}
