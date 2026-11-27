<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add whether assignments can be shared through a public link: enabled, as they could always be shared before.
     */
    public function up(): void
    {
        $this->migrator->add('territories.sharing_enabled', true);
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the setting.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('territories.sharing_enabled');
    }
};
