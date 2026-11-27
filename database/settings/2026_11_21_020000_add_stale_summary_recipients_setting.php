<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add who in the territory department receives the summary of the stale assignments: all its members by default.
     */
    public function up(): void
    {
        $this->migrator->add('territories.stale_summary_overseers_only', false);
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the setting.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('territories.stale_summary_overseers_only');
    }
};
