<?php

namespace App\Settings;

class GeneralSettings extends ConfigSettings
{
    public ?string $name;

    public ?string $locale;

    public ?string $timezone;

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
