<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add whether the users can connect AI assistants through the MCP server: off, as they read personal data, until
     * the super administrators turn it on.
     */
    public function up(): void
    {
        $this->migrator->add('security.ai_assistants_enabled', false);
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the setting.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('security.ai_assistants_enabled');
    }
};
