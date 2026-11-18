<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Add the settings changed from the Settings pages, empty so that they follow `.env` until changed.
     */
    public function up(): void
    {
        $this->migrator->add('general.name');
        $this->migrator->add('general.locale');
        $this->migrator->add('general.timezone');

        $this->migrator->add('territories.map_default_center');
        $this->migrator->add('territories.shared_pii');
    }
};
