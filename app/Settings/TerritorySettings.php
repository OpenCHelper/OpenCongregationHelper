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
     * Time of the check of the stale assignments, as "HH:MM", in the timezone of the application (`APP_TIMEZONE`).
     */
    public string $stale_check_time = '09:00';

    /**
     * Months in which the assignments of a territory are counted to tell how frequently it is assigned (dashboard map).
     */
    public int $frequency_period_months = 12;

    /**
     * Assignments in the period from which a territory is assigned with a medium frequency (fewer: low frequency).
     */
    public int $medium_frequency_min_assignments = 1;

    /**
     * Assignments in the period from which a territory is assigned frequently.
     */
    public int $high_frequency_min_assignments = 2;

    /**
     * Periods, in months, the territory coverage of the dashboard can be calculated on.
     *
     * @var list<int>
     */
    public array $coverage_periods_months = [6, 12, 24];

    /**
     * Period, in months, the territory coverage of the dashboard is calculated on by default (one of the periods).
     */
    public int $coverage_default_period_months = 12;

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
