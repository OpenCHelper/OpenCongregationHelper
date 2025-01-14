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
use Illuminate\Support\Facades\Log;

class TerritoryGlobalMap extends MapWidget
{
    protected int | string | array $columnSpan = 2;
    protected bool $hasBorder = true;

    public function boot(): void
    {
        $polygones = $this->getPolygones();
        $bounds = Arr::flatten(array_map(function ($polygone) {
            return $polygone->getLatlngs()[0];
        }, $polygones), 1);

        $this
            ->actions([
                Actions\CenterMapAction::make()
                    ->centerOnUserPosition()
                    ->zoom(13)
                    ->label('Centra su posizione'),
                Actions\FullscreenAction::make(),
                // Actions\Action::make('mode')
                //     ->icon('filamentmapsicon-o-square-3-stack-3d')
                //     ->alpineClickHandler(<<<JS
                //         if (confirm('Vuoi davvero cambiare la modalità della mappa?')) {
                //             \$dispatch('{$this->getMapActionId()}', 'mode');
                //         }
                //     JS),
                // Actions\Action::make('form')
                //     ->icon('filamentmapsicon-o-square-3-stack-3d')
                //     ->form([
                //         Forms\Components\Select::make('tileLayer')
                //             ->label('Tile Layer')
                //             ->required()
                //             ->default('OpenStreetMap')
                //             ->options([
                //                 'OpenStreetMap' => 'OpenStreetMap',
                //                 'OpenTopoMap' => 'OpenTopoMap',
                //                 'Stadia.AlidadeSatellite' => 'Stadia Alidade Satellite',
                //             ]),
                //     ])
                //     ->action(function (array $data, self $livewire) {
                //         Log::info("Setting Tile Layer to: '" . $data['tileLayer'] . "'");
                //         $livewire->tileLayerMode(trim($data['tileLayer']));
                //         $livewire->dispatch('updateTileLayer');
                //         $livewire->dispatch('refresh');
                //     }),
            ])
            ->mapOptions([
                'zoom' => 13,
            ])
            ->tileLayerUrl([
                'OpenStreetMap' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'OpenTopoMap' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'Stadia.AlidadeSatellite' => 'https://tiles.stadiamaps.com/tiles/alidade_satellite/{z}/{x}/{y}{r}.{ext}'
            ])
            ->tileLayerOptions([
                'OpenStreetMap' => [
                    'attribution' => 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
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
            ])
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
                        <a href='" . htmlspecialchars(TerritoryResource::getUrl('edit', [
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
