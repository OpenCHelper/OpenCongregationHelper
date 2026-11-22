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
};
