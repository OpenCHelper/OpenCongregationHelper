<?php

namespace App\Settings\Casts;

use Spatie\LaravelSettings\SettingsCasts\SettingsCast;

/**
 * Makes a setting follow a configuration value (i.e. `.env`) while it is not changed from the Settings pages.
 *
 * The setting is stored as `null` until changed, and set back to `null` when given the configured value again, so that
 * it follows `.env` again.
 */
class ConfigFallbackCast implements SettingsCast
{
    public function __construct(protected string $configKey) {}

    /**
     * Get the stored value, or the configured one when it is not set.
     */
    public function get($payload): mixed
    {
        return $payload ?? config($this->configKey);
    }

    /**
     * Store the value, or `null` when it is the configured one.
     */
    public function set($payload): mixed
    {
        return $payload === config($this->configKey) ? null : $payload;
    }
}
