<?php

namespace App\Support;

use App\Providers\AppVersionProvider;
use Composer\InstalledVersions;

/**
 * Versions shown in the "About" modal of the user menu.
 */
class AppVersions
{
    /**
     * Versions of the app and, outside production, of its stack.
     *
     * @return array<string, string> Version by name of the component
     */
    public static function all(): array
    {
        $versions = [];

        if (! app()->isProduction()) {
            $versions = [
                'Laravel' => (string) InstalledVersions::getPrettyVersion('laravel/framework'),
                'Filament' => (string) InstalledVersions::getPrettyVersion('filament/filament'),
                'PHP' => PHP_VERSION,
            ];
        }

        $versions['App'] = (string) config('app.version');

        return array_map(fn (string $version): string => ltrim($version, 'v'), $versions);
    }

    /**
     * Details of the build of the app, `null` when they are unknown.
     *
     * @return array{branch: ?string, commit: ?string, date: ?string}
     */
    public static function build(): array
    {
        $app = new AppVersionProvider;

        return [
            'branch' => $app->getBranch(),
            'commit' => $app->getCommit(),
            'date' => $app->getCommitDateTime(),
        ];
    }
}
