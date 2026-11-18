<?php

namespace App\Settings;

use Illuminate\Console\Scheduling\Schedule;

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

    /**
     * Days after which an assignment in progress is stale: its assignee is reminded to return it from then on.
     */
    public int $stale_after_days = 60;

    /**
     * Days after which an assignment in progress is considered lost.
     */
    public int $dead_after_days = 150;

    /**
     * Day of the week of the check of the stale assignments (0 = Sunday, 1 = Monday, …).
     */
    public int $stale_check_day = Schedule::MONDAY;

    /**
     * Time of the check of the stale assignments, as "HH:MM", in the timezone of the application.
     */
    public string $stale_check_time = '09:00';

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
