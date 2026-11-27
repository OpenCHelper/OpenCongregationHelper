<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the address of the Kingdom Hall, empty until set from the Settings pages.
     */
    public function up(): void
    {
        $this->migrator->add('general.kingdom_hall_address');
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the setting.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('general.kingdom_hall_address');
    }
};
