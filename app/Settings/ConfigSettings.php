<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Settings that replace configuration values, changed by the super administrators from the Settings pages.
 *
 * A property is `null` while it was never changed (or was set back to its default): the configuration then keeps
 * the value of `.env`. The other values are applied over the configuration on every request (see `SettingsOverrides`),
 * so the code keeps reading them with `config()`.
 */
abstract class ConfigSettings extends Settings
{
    /**
     * The configuration that each property replaces.
     *
     * @return array<string, string>
     */
    abstract public static function configKeys(): array;
}
