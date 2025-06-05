<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TerritoryResource;
use Webbingbrasil\FilamentMaps\Widgets\MapWidget;
use Webbingbrasil\FilamentMaps\Actions;
use Webbingbrasil\FilamentMaps\Polygone;
use Filament\Forms\Components;
use Illuminate\Support\Arr;

use App\Models\Territory;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Log;

class TerritoryGlobalMap extends MapWidget
{
    use InteractsWithPageFilters;
    
    protected int | string | array $columnSpan = 2;
    protected bool $hasBorder = false;

    protected $listeners = ['updateMap' => 'mapUpdated', 'refreshComponent' => '$refresh'];

    public string $defaultView = 'zone';
    public string $activeTileLayer = 'OpenStreetMap';
    public string $shownTerritoryTypes = 'residential';

    // More layers on https://leaflet-extras.github.io/leaflet-providers/preview/
    public static function getTileLayers(): array
    {
        return array(
            "urls" => [
                'OpenStreetMap' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'OpenStreetMap.HOT' => 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                'OpenTopoMap' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'Stadia.AlidadeSatellite' => 'https://tiles.stadiamaps.com/tiles/alidade_satellite/{z}/{x}/{y}{r}.{ext}',
                'Stadia.OSMBright' => 'https://tiles.stadiamaps.com/tiles/osm_bright/{z}/{x}/{y}{r}.{ext}',
                'Stadia.Outdoors' => 'https://tiles.stadiamaps.com/tiles/outdoors/{z}/{x}/{y}{r}.{ext}',
                'CartoDB.Voyager' => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            ],
            "options" => [
                'OpenStreetMap' => [
                    'attribution' => 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
                ],
                'OpenStreetMap.HOT' => [
                    'maxZoom' => 19,
                	'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Tiles style by <a href="https://www.hotosm.org/" target="_blank">Humanitarian OpenStreetMap Team</a> hosted by <a href="https://openstreetmap.fr/" target="_blank">OpenStreetMap France</a>',
                ],
                'OpenTopoMap' => [
                    'attribution' => 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors, SRTM | Map style © <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
                ],
                'Stadia.AlidadeSatellite' => [
                    'minZoom' => 0,
                	'maxZoom' => 20,
                	'attribution' => '&copy; CNES, Distribution Airbus DS, © Airbus DS, © PlanetObserver (Contains Copernicus Data) | &copy; <a href="https://www.stadiamaps.com/" target="_blank">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                	'ext' => 'jpg'
                ],
                'Stadia.OSMBright' => [
                    'minZoom' => 0,
                	'maxZoom' => 20,
                	'attribution' => '&copy; <a href="https://www.stadiamaps.com/" target="_blank">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                	'ext' => 'png'
                ],
                'Stadia.Outdoors' => [
                    'minZoom' => 0,
                	'maxZoom' => 20,
                	'attribution' => '&copy; <a href="https://www.stadiamaps.com/" target="_blank">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                	'ext' => 'png',
                ],
                'CartoDB.Voyager' => [
                    'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    'subdomains' => 'abcd',
                    'maxZoom' => 20,
                ],                
            ]
        );
    }

    public function boot(): void
    {
        $polygones = $this->getPolygones();
        $bounds = Arr::flatten(array_map(function ($polygone) {
            return $polygone->getLatlngs()[0];
        }, $polygones), 1);

        $this
            ->actions([
                Actions\CenterMapAction::make()
                    ->centerOnUserPosition(label: __('filament-widgets.territory_global_map.center_on_user_position'))
                    ->zoom(13)
                    ->label(__('filament-widgets.territory_global_map.center_on_position')),
                Actions\FullscreenAction::make(),
                Actions\Action::make('form')
                    ->label(__('filament-widgets.territory_global_map.map_settings'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->form([
                        // Components\Select::make('territoryType')
                        //     ->required()
                        //     ->default($this->getShownTerritoryTypes())
                        //     ->label('Tipologia di territori')
                        //     ->options([
                        //         "residential" => "Residenziali",
                        //         "commercial" => "Commerciali",
                        //         "both" => "Entrambi",
                        //     ])
                        //     ->selectablePlaceholder(false),
                        Components\Select::make('mapType')
                            ->required()
                            ->default($this->getActiveTileLayer())
                            ->label(__('filament-widgets.territory_global_map.map_style'))
                            ->options(function () {
                                $tileLayers = TerritoryGlobalMap::getTileLayers();
                                
                                # Return an associative array with the keys as both option and value
                                return array_combine(array_keys($tileLayers['urls']), array_keys($tileLayers['urls']));
                            })
                            ->selectablePlaceholder(false),
                        Components\Select::make('visualizationType')
                            ->required()
                            ->default($this->getDefaultView())
                            ->options([
                                "zone" => "Zone",
                                "status" => "Stato assegnazione",
                            ])
                            ->selectablePlaceholder(false)
                    ])
                    ->action(function (array $data, self $livewire) {
                        // $this->setShownTerritoryTypes($data['territoryType']);
                        $this->setActiveTileLayer($data['mapType']);
                        $this->setDefaultView($data['visualizationType']);

                        $tileLayers = TerritoryGlobalMap::getTileLayers();
                        $mapType = $tileLayers['urls'][$data['mapType']];
                        $mapOptions = $tileLayers['options'][$data['mapType']];
                        
                        $mapOptions = json_encode($mapOptions);

                        // ? Use TileLayer.setUrl() to change the map type? [TileLayer.setUrl()](https://leafletjs.com/reference.html#tilelayer-seturl)
                        $this->js(<<<JS
                            L.tileLayer.wms("$mapType", $mapOptions).addTo(window.filamentMaps['app.filament.widgets.territory-global-map']);
                        JS);

                        Log::info("Total Polygones (before purge): " . count($this->polygones));
                        foreach ($livewire->polygones as $polygone) {
                            Log::info("Removing polygone " . $polygone["id"]);
                            $livewire->removePolygone($polygone["id"]);
                        }
                        $livewire->dispatch('$refresh');

                        // Re-render polygones
                        foreach ($this->getPolygones() as $polygone) {
                            Log::info("Adding polygone " . $polygone->toArray()["id"]);
                            $livewire->addPolygone($polygone);
                        }
                        Log::info("Total Polygones (after purge): " . count($this->polygones));

                        $livewire->dispatch('refreshComponent');
                    }),
            ])
            ->mapOptions([
                'zoom' => 13,
            ])
        ;

        // If there are no territories, center the map on the default location
        if (count($bounds) > 0) {
            $this->fitBounds($bounds);
        } else {
            $this->mapOptions([
                'zoom' => 16,
                'center' => [41.1585696,-74.2557685], 
            ]);
        }
    }
    
    public function getPolygones(): array
    {
        $territories = Territory::with('territoryZone')
            ->where('geojson', '!=', null)
            ->where(function ($query) {
                // Log::info("Shown territory types: " . $this->getShownTerritoryTypes());
                if ($this->getShownTerritoryTypes() === 'residential') {
                    $query->where('is_commercial', '=', false);
                } elseif ($this->getShownTerritoryTypes() === 'commercial') {
                    $query->where('is_commercial', '=', true);
                }
            })
            ->get();

        // Log::info("Total territories: " . count($territories));

        $viewType = $this->getDefaultView();

        $polygones = [];
        foreach ($territories as $territory) {
            $geojson = json_decode($territory->geojson, true);

            foreach ($geojson["features"] as $feature) {
                if ($feature["geometry"]["type"] != "Polygon") {
                    continue;
                }
    
                // The first two elements are longitude and latitude, but we need latitude and longitude
                // Swap all the coordinates
                $coords_lat_long = array_map(function($coord) {
                    return array_map(function($c) {
                        return [$c[1], $c[0]];
                    }, $coord);
                }, $feature["geometry"]["coordinates"]);

                $popup = "
                    <h1>" . htmlspecialchars($territory->name) . "</h1>
                    <p>
                        <a href='" . htmlspecialchars(TerritoryResource::getUrl('view', [
                            'record' => $territory->id,
                            ])) . "'>
                            " . __('filament-widgets.territory_global_map.go_to_territory') . "
                        </a>
                    </p>
                ";

                // See https://leafletjs.com/reference.html#polygon-color for the full list of options
                $polygonOptions = [];
                switch ($viewType) {
                    case 'zone':
                        if ($territory->territoryZone->color) {
                            $polygonOptions['color'] = $territory->territoryZone->color;
                        }
                        break;
                    
                    case 'status':
                        $currentAssignment = $territory->currentAssignment();

                        if ($currentAssignment === null || $currentAssignment->ended_at !== null) {
                            $polygonOptions['color'] = 'red';
                        } else {
                            $polygonOptions['color'] = 'green';
                        }
                        break;
                    
                    default:
                        throw new \Exception("Unknown map view type '$viewType'", 1);
                        break;
                }

                $polygones[] = Polygone::make($territory->name)
                    ->latlngs($coords_lat_long)
                    ->options($polygonOptions)
                    ->tooltip($territory->name)
                    ->popup($popup);
            }
        }

        return $polygones;
    }

    public function getDefaultView()
    {
        return $this->defaultView;
    }
    public function setDefaultView($name)
    {
        $this->defaultView = $name;
    }

    public function getActiveTileLayer()
    {
        return $this->activeTileLayer;
    }
    public function setActiveTileLayer($name)
    {
        $this->activeTileLayer = $name;
    }

    public function getShownTerritoryTypes()
    {
        return $this->shownTerritoryTypes;
    }
    public function setShownTerritoryTypes(string $types)
    {
        if (!in_array($types, ['residential', 'commercial', 'both'])) {
            throw new \Exception("Invalid territory type '$types'", 1);
        }
        $this->shownTerritoryTypes = $types;
    }
}
