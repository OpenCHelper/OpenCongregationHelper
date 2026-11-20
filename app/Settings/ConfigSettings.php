<?php

namespace App\Settings;

/**
 * Settings whose properties fall back on configuration values.
 *
 * A property listed in `configKeys()` is `null` while it was never changed from the Settings pages (or was set back to
 * its default): its value is then the one of the configuration, i.e. of `.env`.
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
     * Get the value of a setting: the one changed from the Settings pages, or the configured one.
     */
    public static function value(string $property): mixed
    {
        return parent::value($property) ?? static::configuredValue($property);
    }

    /**
     * Get the values of several settings at once, keyed by property, each falling back on its configured value.
     *
     * @return array<string, mixed>
     */
    public static function values(string ...$properties): array
    {
        $values = parent::values(...$properties);

        foreach ($values as $property => $value) {
            $values[$property] = $value ?? static::configuredValue($property);
        }

        return $values;
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
