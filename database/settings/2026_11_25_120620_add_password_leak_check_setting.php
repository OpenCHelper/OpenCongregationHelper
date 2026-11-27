<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add whether the passwords are checked against the known data leaks: empty, so that it follows `.env`
     * (`PASSWORD_LEAK_CHECK`, on in production by default) until changed from the Settings pages.
     */
    public function up(): void
    {
        $this->migrator->add('security.password_leak_check');
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the setting.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('security.password_leak_check');
    }
};
