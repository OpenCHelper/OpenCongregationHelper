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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Artisan-Token');

        if ($request->header('X-Artisan-Token-Bypass') === "true" && config('app.env') === 'local') {
            return $next($request);
        }
        
        if ($token !== File::get(storage_path('framework/.artisan-token'))) {
            Log::error("Unauthorized request to artisan API by {$request->ip()} with token ".json_encode($token)." and command ".json_encode($request->route('command'))." (params=".json_encode($request->route('params')).")");
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}
