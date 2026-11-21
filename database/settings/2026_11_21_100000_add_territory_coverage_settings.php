<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the periods of the territory coverage of the dashboard.
     */
    public function up(): void
    {
        $this->migrator->add('territories.coverage_periods_months', [6, 12, 24]);
        $this->migrator->add('territories.coverage_default_period_months', 12);
    }
};
