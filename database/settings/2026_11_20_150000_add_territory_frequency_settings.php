<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the period and the thresholds of the assignment frequency of the dashboard map, with the values used so far.
     */
    public function up(): void
    {
        $this->migrator->add('territories.frequency_period_months', 12);
        $this->migrator->add('territories.medium_frequency_min_assignments', 1);
        $this->migrator->add('territories.high_frequency_min_assignments', 2);
    }
};
