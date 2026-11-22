<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send the users with an unverified email to the verification prompt, only while the super administrators require the
 * confirmation of the email address, except the root user (see the Security settings).
 *
 * The setting is read on each request rather than when the routes are registered, so that a change applies right away,
 * even with cached routes.
 */
class EnsureEmailIsVerifiedWhenRequired extends EnsureEmailIsVerified
{
    public function __construct(protected SecuritySettings $settings) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next, $redirectToRoute = null): mixed
    {
        if (! $this->settings->require_email_verification) {
            return $next($request);
        }

        // The root user is never blocked, so that it can always reach the Security settings to turn the requirement
        // off, e.g. after changing its email while the emails cannot be delivered
        $user = $request->user();

        if ($user instanceof User && $user->isRootUser()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
