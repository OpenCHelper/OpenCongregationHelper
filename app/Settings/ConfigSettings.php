<?php

namespace App\Settings;

/**
 * Settings that (also) replace configuration values.
 *
 * A property listed in `configKeys()` is `null` while it was never changed (or was set back to its default): the
 * configuration then keeps the value of `.env`. The other values are applied over the configuration on every request
 * (see `SettingsOverrides`), so the code keeps reading them with `config()`.
 */
abstract class ConfigSettings extends AppSettings
{
    /**
     * The configuration that each property replaces.
     *
     * @return array<string, string>
     */
    abstract public static function configKeys(): array;
}
