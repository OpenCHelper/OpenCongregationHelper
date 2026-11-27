<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use Throwable;

/**
 * The name of the application chosen by the super administrators (see `GeneralSettings::$app_name`), set as
 * `app.name` so that everything reading the configuration (the title, the top bar, the installed app, the emails and
 * the push notifications) shows it.
 *
 * `APP_NAME` keeps naming the installation where it is read directly from the environment (e.g. the session cookie and
 * the cache prefix), so that renaming the application does not log out the users nor empty the cache.
 */
class AppName
{
    /**
     * Set `app.name` from the settings, keeping `APP_NAME` while they cannot be read (e.g. before the migrations ran, or
     * while the database is unreachable, where the error is reported by what needs the database).
     */
    public static function apply(?GeneralSettings $settings = null): void
    {
        try {
            $name = ($settings ?? app(GeneralSettings::class))->app_name;
        } catch (Throwable) {
            return;
        }

        if (filled($name)) {
            config(['app.name' => $name]);
        }
    }
}
