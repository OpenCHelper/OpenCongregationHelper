<?php

namespace App\Settings;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\QueryException;
use ReflectionProperty;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Spatie\LaravelSettings\Settings;

/**
 * Settings changed by the super administrators from the Settings pages.
 *
 * The default value of each property is used while the settings cannot be loaded, e.g. before they are migrated.
 */
abstract class AppSettings extends Settings
{
    /**
     * Get the value of a setting, or its default when the settings cannot be loaded.
     */
    public static function value(string $property): mixed
    {
        try {
            return app(static::class)->{$property};
        } catch (QueryException|MissingSettings|BindingResolutionException) {
            return static::defaultValue($property);
        }
    }

    /**
     * Get the default value of a setting, as declared on its property (`null` when it has none).
     */
    public static function defaultValue(string $property): mixed
    {
        $reflection = new ReflectionProperty(static::class, $property);

        return $reflection->hasDefaultValue() ? $reflection->getDefaultValue() : null;
    }
}
