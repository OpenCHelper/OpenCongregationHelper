<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Translation\HasLocalePreference;

/**
 * Run code in the language of a user, e.g. the queued imports and exports, which run in the default language of the
 * application.
 */
class UserLocale
{
    /**
     * Run the callback in the preferred language of the user, falling back to the current one.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function run(mixed $user, Closure $callback): mixed
    {
        $originalLocale = app()->getLocale();
        $locale = $user instanceof HasLocalePreference
            ? ($user->preferredLocale() ?: $originalLocale)
            : $originalLocale;

        if ($locale === $originalLocale) {
            return $callback();
        }

        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
