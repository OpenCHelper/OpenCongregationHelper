<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the name of the application shown to the users, starting from `APP_NAME`.
     */
    public function up(): void
    {
        $this->migrator->add('general.app_name', config('app.name'));
    }
};
