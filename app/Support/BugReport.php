<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Link to the bug report form of the repository, prefilled with the details of this installation.
 */
class BugReport
{
    /**
     * Template of the bug report issue form, in `.github/ISSUE_TEMPLATE`.
     */
    public const TEMPLATE = '3-bug_report.yml';

    /**
     * URL of a new bug report, with the address and environment of the installation, the versions of the app and of
     * its stack and the browser of the request already filled in.
     */
    public static function url(Request $request): string
    {
        $fields = array_filter([
            'template' => self::TEMPLATE,
            'instance' => $request->getSchemeAndHttpHost(),
            'app_environment' => app()->environment(),
            'version' => self::version(),
            'stack' => self::stack(),
            'browser' => $request->userAgent(),
        ], filled(...));

        return rtrim((string) config('app.repository_url'), '/').'/issues/new?'.http_build_query($fields, encoding_type: PHP_QUERY_RFC3986);
    }

    /**
     * Version of the app and, outside production, the branch and commit it was built from.
     */
    public static function version(): string
    {
        $version = AppVersions::all()['App'];
        $build = AppVersions::build();

        if ($build === null || blank($build['commit'])) {
            return $version;
        }

        return $version.' ('.($build['branch'] ?? '').'#'.substr($build['commit'], 0, 7).')';
    }

    /**
     * Versions of PHP, Laravel and Filament (also in production), as a Markdown list with the versions as code.
     */
    public static function stack(): string
    {
        $stack = AppVersions::stack();

        return implode("\n", array_map(fn (string $name): string => "- {$name}: `{$stack[$name]}`", ['PHP', 'Laravel', 'Filament']));
    }
}
