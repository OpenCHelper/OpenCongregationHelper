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

    public function setUp(): void
    {
        $this
            ->actions([])
        ;
    }
    
    public function getPolygones(): array
    {
        $territories = Territory::all()->where('geojson', '!=', null);
        
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
                    <h1>" . htmlspecialchars($territory->id_territorio) . "</h1>
                    <br>
                    <p>
                        <a href='" . htmlspecialchars(TerritoryResource::getUrl('edit', [
                            'record' => $territory->id,
                            ])) . "'>
                            Vai al territorio
                        </a>
                    </p>
                ";

                $polygones[] = Polygone::make($territory->id_territorio)
                    ->latlngs($coords_lat_long)
                    ->tooltip($territory->id_territorio)
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

}
