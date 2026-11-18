<?php

namespace App\Settings;

class GeneralSettings extends ConfigSettings
{
    public ?string $name;

    public ?string $locale;

    public ?string $timezone;

    /**
     * The logo shown in the top bar instead of the name, as a path on the `public` disk.
     */
    public ?string $logo = null;

    /**
     * The primary colour of the panel, as a hex colour (gray when not set).
     */
    public ?string $primary_color = null;

    public static function group(): string
    {
        return 'general';
    }

    public static function configKeys(): array
    {
        return [
            'name' => 'app.name',
            'locale' => 'app.locale',
            'timezone' => 'app.timezone',
        ];
    }
}
