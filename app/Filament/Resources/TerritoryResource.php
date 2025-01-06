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
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Set;

class TerritoryResource extends Resource
{
    protected static ?string $model = Territory::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

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
                Map::make('location')
                    ->label('Location')
                    ->columnSpanFull()
                    // ->defaultLocation(latitude: 40.4168, longitude: -3.7038)
                    ->afterStateUpdated(function (Set $set, ?array $state): void {
                        $set('geojson',   json_encode($state['geojson'] ?? ''));
                    })
                    ->afterStateHydrated(function ($state, $record, Set $set): void {
                        $set('location', [
                                'geojson' => json_decode(strip_tags($record->geojson ?? ''))
                            ]
                        );
                    })
                    // ->extraStyles([
                    //     'min-height: 150vh',
                    //     'border-radius: 50px'
                    // ])
                    // ->liveLocation(true, false, 5000)
                    ->showMarker(false)
                    ->showFullscreenControl()
                    ->showZoomControl()
                    ->draggable()
                    ->zoom(15)
                    ->showMyLocationButton()
                    ->geoMan(true)
                    ->geoManEditable(false)
                    ->geoManPosition('topleft')
                    ->rotateMode(false)
                    ->clickable(false) //click to move marker
                    ->drawMarker(false)
                    ->drawPolygon(true)
                    ->drawPolyline(false)
                    ->drawCircle(false)
                    ->drawCircleMarker(false)
                    ->dragMode(false)
                    ->cutPolygon(false)
                    ->editPolygon()
                    ->deleteLayer()
                    ->setColor('#3388ff')
                    ->setFilledColor('#cad9ec'),
                Forms\Components\TextInput::make('geojson')
                    // ->hiddenLabel()
                    ->readOnly(),
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
