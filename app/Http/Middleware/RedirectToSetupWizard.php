<?php

namespace App\Http\Middleware;

use App\Filament\Pages\SetupWizard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Open the first-run setup wizard instead of the first page after the root user logs in, while the setup is not
 * completed (see `OfferSetupWizardAfterLogin`). The next pages show a reminder instead, so that the wizard can be left.
 *
 * The pages of the authentication (e.g. the set-up of the required two-factor authentication) leave it to the first
 * page of the app.
 */
class RedirectToSetupWizard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('GET')
            && ! $request->routeIs('filament.*.auth.*', SetupWizard::getRouteName())
            && $request->session()->pull(SetupWizard::OFFER_SESSION_KEY)
            && SetupWizard::canAccess()
        ) {
            return redirect()->to(SetupWizard::getUrl());
        }

        return $next($request);
    }
}
