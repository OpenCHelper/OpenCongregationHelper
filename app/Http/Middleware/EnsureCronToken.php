<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the endpoints triggered by external cron services (queue and
 * scheduler processing) with a shared secret sent in the `X-Cron-Token` header.
 */
class EnsureCronToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('app.artisan_cron_token');
        $providedToken = (string) $request->header('X-Cron-Token', '');

        if ($expectedToken === '') {
            Log::error('ARTISAN_CRON_TOKEN is not configured: cron endpoints are disabled.');

            return response()->json(['error' => 'Token not setup, access unavailable.'], 401);
        }

        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            Log::warning('Unauthorized request to cron endpoint', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
