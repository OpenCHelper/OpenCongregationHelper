<?php

use App\Http\Middleware\EnsureAiAssistantsAreEnabled;
use App\Mcp\Servers\CongregationServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI Routes
|--------------------------------------------------------------------------
|
| The MCP server that the AI assistants (e.g. ChatGPT, Claude) connect to, on behalf of a user who authorizes them
| with OAuth (Passport): the assistant discovers the authorization server (`/.well-known/...`), registers itself as a
| client (`/oauth/register`), then the user logs in and approves it (`/oauth/authorize`, see `AppServiceProvider`).
|
*/

Route::middleware(EnsureAiAssistantsAreEnabled::class)->group(function (): void {
    // Discovery and Dynamic Client Registration, open to anyone: limited, so that no one floods the clients table
    Route::middleware('throttle:60,1')->group(fn () => Mcp::oauthRoutes());

    Mcp::web('/mcp', CongregationServer::class)
        ->middleware(['auth:api', 'throttle:mcp'])
        ->name('mcp');
});
