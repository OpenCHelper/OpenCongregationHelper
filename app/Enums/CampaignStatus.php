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

    public static function fromValidUntil(?Carbon $validUntil): self
    {
        if (! $validUntil) {
            return self::ACTIVE;
        }

        if ($validUntil->isPast()) {
            return self::TERMINATED;
        }

        $diffInDays = $validUntil->diffInDays(now());

        return match (true) {
            $diffInDays <= 0 => self::ALMOST_EXPIRED,
            $diffInDays <= 3 => self::ALMOST_EXPIRED,
            $diffInDays <= 7 => self::IS_EXPIRING,
            default => self::ACTIVE,
        };
    }

    public static function fromCampaign(Campaign $campaign): self
    {
        return self::fromValidUntil($campaign->valid_until ? Carbon::parse($campaign->valid_until) : null);
    }
}
