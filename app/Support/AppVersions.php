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
        $versions = app()->isProduction() ? [] : self::stack();

        $versions['App'] = ltrim((string) config('app.version'), 'v');

        return $versions;
    }

    /**
     * Versions of the stack the app runs on.
     *
     * @return array{Laravel: string, Filament: string, PHP: string}
     */
    public static function stack(): array
    {
        return array_map(fn (string $version): string => ltrim($version, 'v'), [
            'Laravel' => (string) InstalledVersions::getPrettyVersion('laravel/framework'),
            'Filament' => (string) InstalledVersions::getPrettyVersion('filament/filament'),
            'PHP' => PHP_VERSION,
        ]);
    }

    /**
     * Details of the build of the app (each `null` when unknown), not shown in production.
     *
     * @return array{branch: ?string, commit: ?string, date: ?string}|null
     */
    public static function build(): ?array
    {
        if (app()->isProduction()) {
            return null;
        }

        $app = new AppVersionProvider;

        return [
            'branch' => $app->getBranch(),
            'commit' => $app->getCommit(),
            'date' => $app->getCommitDateTime(),
        ];
    }
}
