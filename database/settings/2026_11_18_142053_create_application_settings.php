<?php

use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the settings changed from the Settings pages: the ones replacing configuration values are empty, so that they
     * follow `.env` until changed, the others have their default value.
     */
    public function up(): void
    {
        $this->migrator->add('general.name');
        $this->migrator->add('general.locale');
        $this->migrator->add('general.timezone');
        $this->migrator->add('general.logo');
        $this->migrator->add('general.primary_color');

        $this->migrator->add('territories.map_default_center');
        $this->migrator->add('territories.shared_pii');
        $this->migrator->add('territories.stale_after_days', 60);
        $this->migrator->add('territories.very_stale_after_days', 90);
        $this->migrator->add('territories.extremely_stale_after_days', 120);
        $this->migrator->add('territories.dead_after_days', 150);
        $this->migrator->add('territories.stale_check_day', Schedule::MONDAY);
        $this->migrator->add('territories.stale_check_time', '09:00');

        $this->migrator->add('campaigns.expiring_days', 7);
        $this->migrator->add('campaigns.almost_expired_days', 3);

        $this->migrator->add('notifications.mail_from_name');
    }
};
