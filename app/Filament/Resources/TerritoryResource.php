<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryResource\Pages;
use App\Filament\Resources\TerritoryResource\RelationManagers;
use App\Models\Territory;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
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
use Illuminate\Database\Eloquent\Model;

class TerritoryResource extends Resource
{
    protected static ?string $model = Territory::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                GazeBanner::make()
                    ->lock()
                    ->columnSpanFull()
                    // ->canTakeControl(fn() => auth()->user()->isAdmin())
                    ->hideOnCreate(),
                Forms\Components\Section::make('Informazioni fondamentali')
                    ->description('Informazioni necessarie per l\'identificazione del territorio')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Select::make('territory_zone_id')
                            ->required()
                            ->relationship(name: 'territoryZone', titleAttribute: 'name')
                            ->preload()
                            ->searchable(),
                        Forms\Components\Toggle::make('is_commercial')
                            // ->inline(false)
                            ->onIcon('heroicon-o-building-storefront')
                            ->offIcon('heroicon-o-home'),
                        Forms\Components\Tabs::make('Tabs')
                            ->columnSpanFull()
                            ->contained(false)
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('TabImage')
                                    ->label('Immagine')
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->extraAttributes(['style' => 'min-height: 320px;'])
                                            ->image()
                                            ->panelLayout('integrated')
                                            ->imageEditor()
                                            ->openable()
                                            ->previewable(),
                                    ]),
                                Forms\Components\Tabs\Tab::make('TabMap')
                                    ->label('Mappa')
                                    ->icon('heroicon-o-map')
                                    ->schema([
                                        Map::make('location')
                                            ->label('Location')
                                            ->columnSpanFull()
                                            ->dehydrated(false)
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
                                ]),
                            ])
                            ->activeTab(function (?Model $record) {
                                return $record?->geojson ? 2 : 1;
                            })
                    ]),
                Forms\Components\Section::make('Informazioni aggiuntive')
                    ->description('Informazioni utili in fase di lavorazione e/o gestione del territorio')
                    ->columns(1)
                    ->schema([
                        // Forms\Components\TextInput::make('dimensione')
                        //     ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->helperText('Inserisci eventuali note utili per la lavorazione del territorio')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('territoryZone.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image_path')
                    ->visibility('private')
                    ->square(),
                // Tables\Columns\TextColumn::make('dimensione')
                //     ->numeric()
                //     ->sortable(),
                Tables\Columns\IconColumn::make('is_commercial')
                    ->boolean(),
                // Tables\Columns\TextColumn::make('activeAssignment.assegnato_a.nome')
                //     ->default('')
                //     ->sortable()
                //     ->searchable(),
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
