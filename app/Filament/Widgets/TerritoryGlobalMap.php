<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TerritoryResource;
use Webbingbrasil\FilamentMaps\Widgets\MapWidget;
use Webbingbrasil\FilamentMaps\Actions;
use Webbingbrasil\FilamentMaps\Marker;
use Webbingbrasil\FilamentMaps\Polygone;

use App\Models\Territory;
use Illuminate\Support\Facades\Log;

class TerritoryGlobalMap extends MapWidget
{
    protected int | string | array $columnSpan = 2;
    protected bool $hasBorder = true;

    // public array $mapOptions = ['center' => [41.896961975616, 12.481241781767], 'zoom' => 13];
    public function setUp(): void
    {
        $this
            ->actions([])
            // ->mapOptions(['center' => [41.896961975616, 12.481241781767], 'zoom' => 13])
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

    public function getActions(): array
    {
        return [
            Actions\CenterMapAction::make()->centerOnUserPosition()->zoom(13),
        ];
    }

    public function getMapOptions(): array
    {
        return [
            'center' => [41.896961975616, 12.481241781767],
            'zoom' => 13,
        ];
    }

}
