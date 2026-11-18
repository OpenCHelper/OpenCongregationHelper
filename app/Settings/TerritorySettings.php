<?php

namespace App\Settings;

class TerritorySettings extends ConfigSettings
{
    /**
     * Where the maps open while no territory has a map yet, as [latitude, longitude].
     *
     * @var float[]|null
     */
    public ?array $map_default_center;

    /**
     * Whether the public page of a shared assignment shows the assignee and the notes of the territory.
     */
    public ?bool $shared_pii;

    public static function group(): string
    {
        return 'territories';
    }

    public static function configKeys(): array
    {
        return [
            'map_default_center' => 'filament-leaflet.default_map_center',
            'shared_pii' => 'app.enable_shared_pii',
        ];
    }
}
