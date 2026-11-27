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

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the settings.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('territories.coverage_periods_months');
        $this->migrator->deleteIfExists('territories.coverage_default_period_months');
    }
};
