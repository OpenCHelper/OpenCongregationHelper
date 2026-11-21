<?php

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send the users with an unverified email to the verification prompt, only while the super administrators require the
 * confirmation of the email address (see the Security settings).
 *
 * The setting is read on each request rather than when the routes are registered, so that a change applies right away,
 * even with cached routes.
 */
class EnsureEmailIsVerifiedWhenRequired extends EnsureEmailIsVerified
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next, $redirectToRoute = null): mixed
    {
        if (! SecuritySettings::value('require_email_verification')) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
