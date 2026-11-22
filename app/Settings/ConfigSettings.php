<?php

namespace App\Settings;

use App\Settings\Casts\ConfigFallbackCast;

/**
 * Settings whose properties fall back on configuration values.
 *
 * A property listed in `configKeys()` is stored as `null` while it was never changed from the Settings pages (or was
 * set back to its default): it then reads as the value of the configuration, i.e. of `.env` (see `ConfigFallbackCast`).
 */
abstract class ConfigSettings extends AppSettings
{
    /**
     * The configuration that each property falls back on.
     *
     * @return array<string, string>
     */
    abstract public static function configKeys(): array;

    /**
     * @return array<string, ConfigFallbackCast>
     */
    public static function casts(): array
    {
        return array_map(
            fn (string $configKey): ConfigFallbackCast => new ConfigFallbackCast($configKey),
            static::configKeys(),
        );
    }

    /**
     * Get the configured value of a setting (from `.env`), used while it is not changed from the Settings pages.
     */
    public static function configuredValue(string $property): mixed
    {
        $configKey = static::configKeys()[$property] ?? null;

        return $configKey !== null ? config($configKey) : static::defaultValue($property);
    }
}
