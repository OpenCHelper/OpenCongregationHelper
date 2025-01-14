<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TerritoryGlobalMap;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Log;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;
    // protected static ?string $navigationIcon = 'heroicon-o-document-text';

    // protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array {
        return [
            TerritoryGlobalMap::class,
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make()
                    ->schema([
                        Components\Select::make('mapType')
                            ->required()
                            ->default('OpenStreetMap')
                            ->label('Tipo di mappa')
                            ->options([
                                'OpenStreetMap' => 'OpenStreetMap',
                                'OpenTopoMap' => 'OpenTopoMap',
                                'Stadia.AlidadeSatellite' => 'Stadia Alidade Satellite',
                            ])
                            ->live(debounce: 0)
                            ->afterStateUpdated(function (Get $get) {
                                $tileLayers = TerritoryGlobalMap::getTileLayers();
                                $mapType = $tileLayers['urls'][$get('mapType')];
                                $mapOptions = $tileLayers['options'][$get('mapType')];
                                
                                $mapOptions = json_encode($mapOptions);

                                $this->js(<<<JS
                                    L.tileLayer.wms("$mapType", $mapOptions).addTo(window.filamentMaps['app.filament.widgets.territory-global-map']);
                                JS);
                            })
                            ->selectablePlaceholder(false)
                    ])
                    ->columns(3),
            ]);
    }

}
