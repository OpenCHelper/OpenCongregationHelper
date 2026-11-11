<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureArtisanToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Artisan-Token-Bypass') === 'true' && app()->environment('local')) {
            return $next($request);
        }

        $tokenFile = storage_path('framework/.artisan-token');

        if (! File::exists($tokenFile)) {
            Log::error('Path to .artisan-token file does not exist. Please create it and add a token ('.$tokenFile.').');

            return response()->json(['error' => 'Token not setup, access unavailable.'], 401);
        }

        $expectedToken = trim(File::get($tokenFile));
        $providedToken = (string) $request->header('X-Artisan-Token', '');

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            Log::error('Unauthorized request to artisan API', [
                'ip' => $request->ip(),
                'command' => $request->route('command') ?? $request->input('command'),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
