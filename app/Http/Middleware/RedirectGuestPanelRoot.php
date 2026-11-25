<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the root of the guest panel to the welcome page: the panel only serves the pages opened from share links, and
 * has no dashboard or navigation to show there.
 *
 * Filament still registers its home route at the root, and with no page to open it would redirect to itself. A route
 * in `routes/web.php` can't take its place: with the routes cached, Filament's route is matched first.
 */
class RedirectGuestPanelRoot
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panelPath = trim(Filament::getCurrentOrDefaultPanel()->getPath(), '/');

        if (trim($request->path(), '/') === $panelPath) {
            return redirect('/');
        }

        return $next($request);
    }
}
