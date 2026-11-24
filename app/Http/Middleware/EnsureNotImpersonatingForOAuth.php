<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use STS\FilamentImpersonate\Facades\Impersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the user can authorize an AI assistant, never an administrator impersonating them: on every step of the OAuth
 * authorization (Passport), as it skips the consent page when the user already approved the same assistant.
 */
class EnsureNotImpersonatingForOAuth
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('passport.authorizations.*') && Impersonation::isImpersonating()) {
            abort(403, __('ai-assistants.authorize.impersonating'));
        }

        return $next($request);
    }
}
