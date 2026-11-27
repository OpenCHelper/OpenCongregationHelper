<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add whether the users must set up two-factor authentication and confirm their email address: both off, as until
     * now neither was required.
     */
    public function up(): void
    {
        $this->migrator->add('security.require_two_factor', false);
        $this->migrator->add('security.require_email_verification', false);
    }

    /**
     * Reverse the migrations, so that running them again (e.g. after `migrate:rollback`) does not find the settings.
     */
    public function down(): void
    {
        $this->migrator->deleteIfExists('security.require_two_factor');
        $this->migrator->deleteIfExists('security.require_email_verification');
    }
};
