<?php

namespace App\Settings;

class CampaignSettings extends AppSettings
{
    /**
     * Days before its end date from which a campaign is expiring.
     */
    public int $expiring_days = 7;

    /**
     * Days before its end date from which a campaign is almost expired.
     */
    public int $almost_expired_days = 3;

    public static function group(): string
    {
        return 'campaigns';
    }
}
