<?php

namespace App\Http\Middleware;

use App\Filament\Setup\Pages\SetupWizard;
use App\Support\Installation;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Open the setup wizard instead of the pages of the application while it is not installed (see `Installation`).
 */
class RedirectToSetupWizard
{
    /**
     * The routes that work before the installation: the wizard itself and what its page loads.
     */
    private const ALLOWED_ROUTES = ['web-manifest', 'offline', 'storage.public', 'livewire.*'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('GET')
            && ! $request->routeIs(SetupWizard::getRouteName(Filament::getPanel('setup')), ...self::ALLOWED_ROUTES)
            && Installation::needsSetup()
        ) {
            return redirect()->to(SetupWizard::getUrl(panel: 'setup'));
        }

        return $next($request);
    }
}
