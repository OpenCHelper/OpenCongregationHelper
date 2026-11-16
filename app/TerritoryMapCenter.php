<?php

namespace App;

use App\Models\Territory;
use Illuminate\Support\Facades\Cache;

/**
 * Resolve where the maps open when they have nothing to show yet (e.g. the map of a new territory).
 *
 * The centre is the median of the centres of the territories that have a map, so that it follows the
 * area of the congregation, and a territory drawn far away (or a few separate areas) does not pull it
 * into the middle of nowhere like an average would. Only when no territory has a map yet, the configured
 * default centre is used (`MAP_DEFAULT_CENTER`, see `config/filament-leaflet.php`).
 *
 * The centres of the territories are cached, and the cache is cleared whenever a territory changes
 * (see `TerritoryObserver`). Zones need not clear it: one can be deleted only without territories.
 */
class TerritoryMapCenter
{
    /**
     * The cache key of the centres of the territories, grouped by zone.
     */
    public const CACHE_KEY = 'territory-map-centers';

    /**
     * How long the centres are cached, as a safety net for the changes made without model events
     * (e.g. by the seeders): the observers clear the cache on every change anyway.
     */
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * Get the centre of the maps, as [latitude, longitude].
     *
     * Given a zone, the centre of its territories, if any has a map; otherwise the centre of all
     * the territories, and the configured default when no territory has a map.
     *
     * @return array{float, float}
     */
    public static function resolve(?int $territoryZoneId = null): array
    {
        $centersByZone = static::centersByZone();

        $centers = $territoryZoneId !== null ? ($centersByZone[$territoryZoneId] ?? []) : [];

        if ($centers === []) {
            $centers = array_merge(...array_values($centersByZone));
        }

        if ($centers === []) {
            /** @var array{float, float} $default */
            $default = config('filament-leaflet.default_map_center');

            return $default;
        }

        return [
            static::median(array_column($centers, 0)),
            static::median(array_column($centers, 1)),
        ];
    }

    /**
     * Forget the cached centres, after a territory changed.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get the centres of the territories with a map, as [latitude, longitude], grouped by zone.
     *
     * @return array<int, list<array{float, float}>>
     */
    protected static function centersByZone(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $centersByZone = [];

            Territory::query()
                ->whereNotNull('geojson')
                ->select(['id', 'territory_zone_id', 'geojson'])
                ->each(function (Territory $territory) use (&$centersByZone): void {
                    $center = static::centerOf($territory->geojson);

                    if ($center !== null) {
                        $centersByZone[$territory->territory_zone_id][] = $center;
                    }
                });

            return $centersByZone;
        });
    }

    /**
     * Get the centre of the bounding box of a GeoJSON, as [latitude, longitude], if it has any position.
     *
     * @return array{float, float}|null
     */
    public static function centerOf(?string $geojson): ?array
    {
        $positions = static::positionsOf(json_decode((string) $geojson, true));

        if ($positions === []) {
            return null;
        }

        // GeoJSON positions are [longitude, latitude]
        $longitudes = array_column($positions, 0);
        $latitudes = array_column($positions, 1);

        return [
            (min($latitudes) + max($latitudes)) / 2,
            (min($longitudes) + max($longitudes)) / 2,
        ];
    }

    /**
     * Collect the positions ([longitude, latitude]) of a decoded GeoJSON, whatever its geometries.
     *
     * @return list<array{float, float}>
     */
    protected static function positionsOf(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        if (count($node) >= 2 && is_numeric($node[0] ?? null) && is_numeric($node[1] ?? null)) {
            return [[(float) $node[0], (float) $node[1]]];
        }

        $positions = [];

        foreach (['features', 'geometry', 'geometries', 'coordinates'] as $key) {
            if (array_key_exists($key, $node)) {
                $positions = [...$positions, ...static::positionsOf($node[$key])];
            }
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                $positions = [...$positions, ...static::positionsOf($child)];
            }
        }

        return $positions;
    }

    /**
     * Get the median of a non-empty list of numbers.
     *
     * @param  list<float>  $values
     */
    protected static function median(array $values): float
    {
        sort($values);

        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
