<?php

namespace App\Filament\Widgets;

use Override;
use Closure;

use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Polygon;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Rectangle;
use App\Models\Territory;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\FeatureGroup;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\LayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\MarkerCluster;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Circle;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\CircleMarker;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Polyline;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class TerritoryGlobalMap extends MapWidget
{
    protected int|Closure $maxZoom = 18;
    protected int|Closure $minZoom = 2;

    // Multiple layers
    protected TileLayer|string|array|Closure $tileLayersUrl = [
        'Street Map' => TileLayer::OpenStreetMap,
        'Google Streets' => TileLayer::GoogleStreets,
        'Google Satellite' => TileLayer::GoogleSatellite,
        'Google Hybrid' => TileLayer::GoogleHybrid,
    ];

    #[Override]
    protected function getDefaultZoom(): int
    {
        return 12;
    }

    #[Override]
    protected function getMapHeight(): int
    {
        return 600;
    }

    #[Override]
    public function getColumnSpan(): int|string|array
    {
        return 3;
    }

    #[Override]
    public function getFitBounds(): bool
    {
        return true;
    }

    #[Override]
    public function hasFullscreenControl(): bool
    {
        return true;
    }

    #[Override]
    protected function getShapes(): array
    {
        return $this->getPolygones();
    }

    public function handleMapClick(float $latitude, float $longitude): void
    {
        Notification::make()
            ->title('Map clicked')
            ->body("Coordinates: {$latitude}, {$longitude}")
            ->send();
    }

    public function getPolygones(): array
    {
        $territories = Territory::with(['territoryZone', 'currentAssignment'])
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

        $viewType = "zone";

        $polygones = [];
        foreach ($territories as $territory) {
            $geojson = json_decode($territory->geojson, true);

            foreach ($geojson["features"] as $feature) {
                if ($feature["geometry"]["type"] != "Polygon") {
                    continue;
                }

                // The first two elements are longitude and latitude, but we need latitude and longitude
                // Swap all the coordinates
                $coords_lat_long = array_map(function ($coord) {
                    return array_map(function ($c) {
                        return [$c[1], $c[0]];
                    }, $coord);
                }, $feature["geometry"]["coordinates"]);

                $popup = "";

                // See https://leafletjs.com/reference.html#polygon-color for the full list of options
                $polygonOptions = [];
                switch ($viewType) {
                    case 'zone':
                        if ($territory->territoryZone->color) {
                            $polygonOptions['color'] = $territory->territoryZone->color;
                        }
                        break;

                    case 'status':
                        $currentAssignment = $territory->currentAssignment;

                        if ($currentAssignment === null || $currentAssignment->ended_at !== null) {
                            $polygonOptions['color'] = 'red';
                        } else {
                            $polygonOptions['color'] = 'green';
                        }
                        break;

                    default:
                        throw new \Exception("Unknown map view type '$viewType'", 1);
                }

                $polygones[] = Polygon::make($coords_lat_long)
                    ->fillColor($polygonOptions['color'])
                    ->title($territory->name)
                    ->popupContent($popup)
                    ->group($territory->territoryZone->name)
                    ->action(function (Marker $marker) use ($territory) { // Or ->onClick()
                        Notification::make()
                            ->title("Clicked on territory {$territory->name}!")
                            ->body('ID: ' . $marker->getId())
                            ->send();
                    });;
            }
        }

        return $polygones;
    }

    protected function getShownTerritoryTypes(): string
    {
        return 'residential';
    }
}
