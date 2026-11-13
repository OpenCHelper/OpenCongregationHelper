<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the territories of the dashboard global map are colored.
 */
enum TerritoryMapMode: string implements HasLabel
{
    /** Colored with the color of their zone. */
    case ZONE = 'zone';

    /** Colored by whether they are available to be assigned. */
    case STATUS = 'status';

    /** Colored by how many times they were assigned in the last 12 months. */
    case FREQUENCY = 'frequency';

    /** Colored by their progress in the selected campaign. */
    case CAMPAIGN = 'campaign';

    public function getLabel(): string
    {
        return match ($this) {
            self::ZONE => __('filament-widgets.territory_global_map.mode_zone'),
            self::STATUS => __('filament-widgets.territory_global_map.mode_status'),
            self::FREQUENCY => __('filament-widgets.territory_global_map.mode_frequency'),
            self::CAMPAIGN => __('filament-widgets.territory_global_map.mode_campaign'),
        };
    }

    /**
     * Get the categories the territories are split into, in the order they are listed in the legend.
     *
     * Territories colored by zone have no fixed categories: their legend lists the zones.
     *
     * @return list<TerritoryMapCategory>
     */
    public function getCategories(): array
    {
        return match ($this) {
            self::ZONE => [],
            self::STATUS => [
                TerritoryMapCategory::AVAILABLE,
                TerritoryMapCategory::NOT_AVAILABLE,
            ],
            self::FREQUENCY => [
                TerritoryMapCategory::LOW_FREQUENCY,
                TerritoryMapCategory::MEDIUM_FREQUENCY,
                TerritoryMapCategory::HIGH_FREQUENCY,
            ],
            self::CAMPAIGN => [
                TerritoryMapCategory::OUT_FOR_OTHER_ASSIGNMENT,
                TerritoryMapCategory::TO_BE_ASSIGNED,
                TerritoryMapCategory::IN_PROGRESS,
                TerritoryMapCategory::WORKED,
            ],
        };
    }
}
