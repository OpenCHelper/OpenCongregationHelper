<?php

namespace App\Support;

/**
 * Version of the service worker of the Progressive Web App (`public/sw.js`).
 *
 * The page registers the service worker with this version in its URL, and the service worker names its cache after
 * it: a new version installs a new service worker, which deletes the files cached by the old one.
 */
class ServiceWorker
{
    public static function version(): string
    {
        // The app version changes only on releases: the fingerprint of the cached files also refreshes the cache
        // when the offline page, its translations or the icons change in between (e.g. on a hotfix or a preview).
        $fingerprint = self::fingerprint(self::cachedFiles());

        return config('app.version').'+'.$fingerprint;
    }

    /**
     * Short hash of the content of the given files, which changes whenever one of them changes.
     *
     * @param  list<string>  $paths
     */
    public static function fingerprint(array $paths): string
    {
        $hashes = array_map(fn (string $path): string => hash_file('xxh32', $path), $paths);

        return hash('xxh32', implode(',', $hashes));
    }

    /**
     * Files the service worker caches, or from which the cached offline page is rendered.
     *
     * @return list<string>
     */
    private static function cachedFiles(): array
    {
        return [
            resource_path('views/offline.blade.php'),
            ...glob(lang_path('*/pwa.php')),
            ...glob(public_path('icons/*')),
        ];
    }
}
