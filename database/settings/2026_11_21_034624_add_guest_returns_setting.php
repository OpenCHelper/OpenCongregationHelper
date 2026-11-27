<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add whether the return of an assignment can be asked from its shared page: empty, so that it follows `.env`
     * (`GUEST_ALLOW_RETURNING_ASSIGNMENT`) until changed from the Settings pages.
     */
    public function up(): void
    {
        $this->migrator->add('territories.guest_returns');
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the setting.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('territories.guest_returns');
    }
};
