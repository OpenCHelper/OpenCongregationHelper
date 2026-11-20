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
};
