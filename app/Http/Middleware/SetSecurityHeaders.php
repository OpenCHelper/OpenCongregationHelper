<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the security headers to every response.
 *
 * - `X-Frame-Options: DENY` stops other sites from framing the app (clickjacking). Nothing frames its own pages: the
 *   e-mails of the mail log are shown in an `<iframe srcdoc>`, which has no response and so no header to check.
 * - `X-Content-Type-Options: nosniff` stops browsers from guessing the type of a file (e.g. an upload served as HTML).
 * - `Referrer-Policy: strict-origin-when-cross-origin` sends only the origin to other sites, so that the tokens of the
 *   shared links never leave the app, while the map tiles still get the `Referer` their usage policy requires.
 * - `X-Powered-By` would reveal the PHP version.
 *
 * A header already set by the response is kept.
 */
class SetSecurityHeaders
{
    /**
     * @var array<string, string>
     */
    public const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $response->headers->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
