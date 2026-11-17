<?php

namespace App\Enums;

use App\Models\Campaign;
use Carbon\Carbon;

enum CampaignStatus: string
{
    case ACTIVE = 'active';
    case IS_EXPIRING = 'is_expiring';
    case ALMOST_EXPIRED = 'almost_expired';
    case TERMINATED = 'terminated';

    /**
     * Number of days before its end date from which a campaign is expiring.
     */
    public const EXPIRING_DAYS = 7;

    /**
     * Number of days before its end date from which a campaign is almost expired.
     */
    public const ALMOST_EXPIRED_DAYS = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('filament-resources.campaign.status_active'),
            self::IS_EXPIRING => __('filament-resources.campaign.status_is_expiring'),
            self::ALMOST_EXPIRED => __('filament-resources.campaign.status_almost_expired'),
            self::TERMINATED => __('filament-resources.campaign.status_terminated'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::IS_EXPIRING => 'warning',
            self::ALMOST_EXPIRED => 'danger',
            self::TERMINATED => 'gray',
        };
    }

    /**
     * Get the status of a campaign from the days remaining until its end date.
     *
     * Campaigns without an end date, or with more than 7 days left, are active.
     */
    public static function fromValidUntil(?Carbon $validUntil): self
    {
        if (! $validUntil) {
            return self::ACTIVE;
        }

        if ($validUntil->isPast()) {
            return self::TERMINATED;
        }

        $daysRemaining = now()->diffInDays($validUntil);

        return match (true) {
            $daysRemaining <= self::ALMOST_EXPIRED_DAYS => self::ALMOST_EXPIRED,
            $daysRemaining <= self::EXPIRING_DAYS => self::IS_EXPIRING,
            default => self::ACTIVE,
        };
    }

    public static function fromCampaign(Campaign $campaign): self
    {
        return self::fromValidUntil($campaign->valid_until ? Carbon::parse($campaign->valid_until) : null);
    }
}
