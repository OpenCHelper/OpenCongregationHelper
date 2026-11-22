<?php

namespace App\Settings;

use ReflectionProperty;
use Spatie\LaravelSettings\Settings;

/**
 * Settings changed by the super administrators from the Settings pages.
 *
 * Every property declares a default value, used while it is not migrated yet.
 */
abstract class AppSettings extends Settings
{
    /**
     * Get the default value of a setting, as declared on its property (`null` when it has none).
     */
    public static function defaultValue(string $property): mixed
    {
        $reflection = new ReflectionProperty(static::class, $property);

        return $reflection->hasDefaultValue() ? $reflection->getDefaultValue() : null;
    }
}
