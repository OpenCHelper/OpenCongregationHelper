<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the public pages of the shared links out of search engines: their links can be forwarded anywhere, and they may
 * show personal data (see `TerritorySettings::$shared_pii`).
 *
 * The crawlers must still be allowed to fetch them (see `public/robots.txt`), or they would never read this header and
 * could index the bare links found elsewhere.
 */
class PreventSearchIndexing
{
    public const DIRECTIVES = 'noindex, nofollow, noarchive, nosnippet, noimageindex';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', self::DIRECTIVES);

        return $response;
    }
}
