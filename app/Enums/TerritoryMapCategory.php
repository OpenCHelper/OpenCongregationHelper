<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The category a territory falls in on the dashboard global map, which sets its color.
 *
 * @see TerritoryMapMode::getCategories() for the categories of each map mode.
 */
enum TerritoryMapCategory: string implements HasColor, HasLabel
{
    case AVAILABLE = 'available';
    case NOT_AVAILABLE = 'not_available';

    case LOW_FREQUENCY = 'low_frequency';
    case MEDIUM_FREQUENCY = 'medium_frequency';
    case HIGH_FREQUENCY = 'high_frequency';

    case OUT_FOR_OTHER_ASSIGNMENT = 'out_for_other_assignment';
    case TO_BE_ASSIGNED = 'to_be_assigned';
    case IN_PROGRESS = 'in_progress';
    case WORKED = 'worked';

    /**
     * Number of assignments in the last 12 months from which a territory is assigned with a medium frequency.
     */
    public const MEDIUM_FREQUENCY_MIN_ASSIGNMENTS = 1;

    /**
     * Number of assignments in the last 12 months from which a territory is assigned frequently.
     */
    public const HIGH_FREQUENCY_MIN_ASSIGNMENTS = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => __('filament-widgets.territory_global_map.category_available'),
            self::NOT_AVAILABLE => __('filament-widgets.territory_global_map.category_not_available'),
            self::LOW_FREQUENCY => __('filament-widgets.territory_global_map.category_low_frequency'),
            self::MEDIUM_FREQUENCY => __('filament-widgets.territory_global_map.category_medium_frequency'),
            self::HIGH_FREQUENCY => __('filament-widgets.territory_global_map.category_high_frequency', [
                'count' => self::HIGH_FREQUENCY_MIN_ASSIGNMENTS,
            ]),
            self::OUT_FOR_OTHER_ASSIGNMENT => __('filament-widgets.territory_global_map.category_out_for_other_assignment'),
            self::TO_BE_ASSIGNED => __('filament-widgets.territory_global_map.category_to_be_assigned'),
            self::IN_PROGRESS => __('filament-widgets.territory_global_map.category_in_progress'),
            self::WORKED => __('filament-widgets.territory_global_map.category_worked'),
        };
    }

    /**
     * Get the color palette of the category (see {@see Color}).
     *
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::AVAILABLE, self::HIGH_FREQUENCY, self::WORKED => Color::Green,
            self::MEDIUM_FREQUENCY, self::IN_PROGRESS => Color::Orange,
            self::LOW_FREQUENCY, self::OUT_FOR_OTHER_ASSIGNMENT => Color::Red,
            self::NOT_AVAILABLE, self::TO_BE_ASSIGNED => Color::Gray,
        };
    }

    /**
     * Get the current status of a territory: available unless it has an assignment in progress (not returned yet).
     */
    public static function fromOpenAssignment(bool $hasOpenAssignment): self
    {
        return $hasOpenAssignment ? self::NOT_AVAILABLE : self::AVAILABLE;
    }

    /**
     * Get how frequently a territory is assigned from the number of its assignments started in the last 12 months.
     */
    public static function fromRecentAssignmentsCount(int $recentAssignmentsCount): self
    {
        return match (true) {
            $recentAssignmentsCount >= self::HIGH_FREQUENCY_MIN_ASSIGNMENTS => self::HIGH_FREQUENCY,
            $recentAssignmentsCount >= self::MEDIUM_FREQUENCY_MIN_ASSIGNMENTS => self::MEDIUM_FREQUENCY,
            default => self::LOW_FREQUENCY,
        };
    }

    /**
     * Get the progress of a territory in a campaign.
     *
     * A territory returned at least once for the campaign has been worked, even if it was assigned again afterwards.
     *
     * @param  bool  $isWorkedInCampaign  Whether the territory has a returned assignment linked to the campaign.
     * @param  bool  $isInProgressInCampaign  Whether the territory has an assignment in progress linked to the campaign.
     * @param  bool  $hasOpenAssignment  Whether the territory has an assignment in progress, linked to any campaign or none.
     */
    public static function fromCampaignProgress(bool $isWorkedInCampaign, bool $isInProgressInCampaign, bool $hasOpenAssignment): self
    {
        return match (true) {
            $isWorkedInCampaign => self::WORKED,
            $isInProgressInCampaign => self::IN_PROGRESS,
            $hasOpenAssignment => self::OUT_FOR_OTHER_ASSIGNMENT,
            default => self::TO_BE_ASSIGNED,
        };
    }
}
