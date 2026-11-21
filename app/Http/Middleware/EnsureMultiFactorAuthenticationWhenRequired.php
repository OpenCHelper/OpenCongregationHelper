<?php

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/**
 * Send the users without two-factor authentication to its set-up, only while the super administrators require it (see
 * the Security settings). Otherwise the users can still enable it from their profile.
 *
 * The setting is read on each request rather than when the routes are registered, so that a change applies right away,
 * even with cached routes.
 */
class EnsureMultiFactorAuthenticationWhenRequired extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! SecuritySettings::value('require_two_factor')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
