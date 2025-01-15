<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TerritoryResource;
use Webbingbrasil\FilamentMaps\Widgets\MapWidget;
use Webbingbrasil\FilamentMaps\Actions;
use Webbingbrasil\FilamentMaps\Marker;
use Webbingbrasil\FilamentMaps\Polygone;
use Filament\Forms;
use Illuminate\Support\Arr;

use App\Models\Territory;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Log;

class TerritoryGlobalMap extends MapWidget
{
    use InteractsWithPageFilters;
    
    protected int | string | array $columnSpan = 2;
    protected bool $hasBorder = true;

    protected $listeners = ['$refresh'];

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

        Log::info("Bounds: " . json_encode($bounds));
        Log::info("Filter data: " . json_encode($this->filters));

        $this
            ->actions([
                Actions\CenterMapAction::make()
                    ->centerOnUserPosition()
                    ->zoom(13)
                    ->label('Centra su posizione'),
                Actions\FullscreenAction::make(),
            ])
            ->mapOptions([
                'zoom' => 13,
            ])
            // More layers on https://leaflet-extras.github.io/leaflet-providers/preview/
            ->tileLayerUrl($this->getTileLayers()['urls'])
            ->tileLayerOptions($this->getTileLayers()['options'])
            ->tileLayerMode($this->filters['mapType'] ?? 'OpenStreetMap')
            ->fitBounds($bounds)
        ;
    }
    
    public function getPolygones(): array
    {
        $territories = Territory::with('territoryZone')->where('geojson', '!=', null)->get();
        
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
                            Vai al territorio
                        </a>
                    </p>
                ";

                // See https://leafletjs.com/reference.html#polygon-color for the full list of options
                $polygonOptions = [];
                if ($territory->territoryZone->color) {
                    $polygonOptions['color'] = $territory->territoryZone->color;
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
}
