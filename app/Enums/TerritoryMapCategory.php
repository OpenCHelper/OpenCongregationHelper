<?php

namespace App\Enums;

use App\Settings\TerritorySettings;
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

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => __('filament-widgets.territory_global_map.category_available'),
            self::NOT_AVAILABLE => __('filament-widgets.territory_global_map.category_not_available'),
            self::LOW_FREQUENCY, self::MEDIUM_FREQUENCY, self::HIGH_FREQUENCY => $this->getFrequencyLabel(),
            self::OUT_FOR_OTHER_ASSIGNMENT => __('filament-widgets.territory_global_map.category_out_for_other_assignment'),
            self::TO_BE_ASSIGNED => __('filament-widgets.territory_global_map.category_to_be_assigned'),
            self::IN_PROGRESS => __('filament-widgets.territory_global_map.category_in_progress'),
            self::WORKED => __('filament-widgets.territory_global_map.category_worked'),
        };
    }

    /**
     * Get the label of a frequency category, with the thresholds chosen on the Settings page.
     */
    private function getFrequencyLabel(): string
    {
        $settings = app(TerritorySettings::class);
        $medium = $settings->medium_frequency_min_assignments;
        $high = $settings->high_frequency_min_assignments;

        return match ($this) {
            self::LOW_FREQUENCY => trans_choice('filament-widgets.territory_global_map.category_low_frequency', $medium, [
                'period' => self::getFrequencyPeriod(),
            ]),
            self::MEDIUM_FREQUENCY => $high - $medium > 1
                ? __('filament-widgets.territory_global_map.category_medium_frequency_range', ['min' => $medium, 'max' => $high - 1])
                : trans_choice('filament-widgets.territory_global_map.category_medium_frequency', $medium),
            default => __('filament-widgets.territory_global_map.category_high_frequency', ['count' => $high]),
        };
    }

    /**
     * Describe the period in which the assignments are counted to tell how frequently a territory is assigned (e.g. "in
     * the last 12 months").
     */
    public static function getFrequencyPeriod(): string
    {
        return trans_choice('filament-widgets.territory_global_map.frequency_period', app(TerritorySettings::class)->frequency_period_months);
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
     * Get how frequently a territory is assigned from the number of its assignments started in the period chosen on the
     * Settings page (`frequency_period_months`).
     */
    public static function fromRecentAssignmentsCount(int $recentAssignmentsCount): self
    {
        $settings = app(TerritorySettings::class);

        return match (true) {
            $recentAssignmentsCount >= $settings->high_frequency_min_assignments => self::HIGH_FREQUENCY,
            $recentAssignmentsCount >= $settings->medium_frequency_min_assignments => self::MEDIUM_FREQUENCY,
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
