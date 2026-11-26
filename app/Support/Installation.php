<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Whether the application is installed: its database is set up and has the root user. Until then, every page opens the
 * setup wizard (see `RedirectToSetupWizard`).
 *
 * Once installed, a marker file spares the database query on the next requests. The installations set up from the
 * terminal (`och:install`) are recognized from their root user, and get the marker on their first request.
 */
class Installation
{
    private static ?bool $installed = null;

    public static function isInstalled(): bool
    {
        return self::$installed ??= self::detect();
    }

    /**
     * Whether the setup wizard can be used: while the application is not installed, and the wizard is not turned off.
     */
    public static function needsSetup(): bool
    {
        return config('app.setup.enabled') && ! self::isInstalled();
    }

    public static function markInstalled(): void
    {
        @file_put_contents(self::markerPath(), now()->toIso8601String());

        self::$installed = true;
    }

    /**
     * Forget what was detected, e.g. after the wizard changed the database settings (and between the tests).
     */
    public static function forget(): void
    {
        self::$installed = null;
    }

    /**
     * The file that remembers that the application is installed (`app.setup.marker`, e.g. a temporary one per process in
     * the tests, which run in parallel).
     */
    public static function markerPath(): string
    {
        return (string) (config('app.setup.marker') ?: storage_path('framework/installed'));
    }

    /**
     * Whether the token typed in the wizard is the `SETUP_TOKEN` of the installation (never, while it has none).
     */
    public static function tokenMatches(string $token): bool
    {
        $expected = (string) config('app.setup.token');

        return $expected !== '' && hash_equals($expected, $token);
    }

    private static function detect(): bool
    {
        if (! config('app.setup.enabled') || file_exists(self::markerPath())) {
            return true;
        }

        $connection = config('database.default');

        if (blank(config("database.connections.{$connection}.database"))) {
            return false;
        }

        try {
            $installed = Schema::hasTable((new User)->getTable()) && User::query()->whereKey(1)->exists();
        } catch (Throwable) {
            // The database cannot be reached: an installation in progress (with its `SETUP_TOKEN`) shows the error in
            // the wizard, an installed one shows its usual error page rather than the wizard
            return blank(config('app.setup.token'));
        }

        if ($installed) {
            self::markInstalled();
        }

        return $installed;
    }
}
