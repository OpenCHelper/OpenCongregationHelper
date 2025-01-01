<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryResource\Pages;
use App\Filament\Resources\TerritoryResource\RelationManagers;
use App\Models\Territory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TerritoryResource extends Resource
{
    protected static ?string $model = Territory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id_territorio')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('zone')
                    ->required()
                    ->relationship(titleAttribute: 'nome_zona')
                    ->preload()
                    ->searchable(),
                Forms\Components\FileUpload::make('immagine')
                    ->required(),
                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('dimensione')
                    ->numeric(),
                Forms\Components\Toggle::make('territorio_commerciale'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id_territorio')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('zone.nome_zona')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('immagine')
                    ->visibility('private')
                    ->square(),
                Tables\Columns\TextColumn::make('dimensione')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('territorio_commerciale')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListTerritories::route('/'),
            'create' => Pages\CreateTerritory::route('/create'),
            'edit' => Pages\EditTerritory::route('/{record}/edit'),
        ];
    }
}
