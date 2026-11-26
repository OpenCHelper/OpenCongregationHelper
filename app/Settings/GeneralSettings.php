<?php

namespace App\Settings;

class GeneralSettings extends AppSettings
{
    /**
     * The name of the application shown to the users (title, top bar, installed app, emails), replacing `APP_NAME` at
     * runtime (see `AppName`). `APP_NAME` only names the installation (e.g. the session cookie and the cache prefix),
     * and gives its first value.
     */
    public ?string $app_name = null;

    /**
     * Whether the first-run setup wizard was completed or skipped by the root user (see `SetupWizard`).
     */
    public bool $setup_completed = false;

    /**
     * The address of the Kingdom Hall of the congregation.
     */
    public ?string $kingdom_hall_address = null;

    /**
     * The logo shown in the top bar instead of the name, as a path on the `public` disk.
     */
    public ?string $logo = null;

    /**
     * The primary colour of the users who did not choose one in their UI preferences, as a hex colour (the default of
     * `config/ui-switcher.php` when not set).
     */
    public ?string $primary_color = null;

    public static function group(): string
    {
        return 'general';
    }
}
