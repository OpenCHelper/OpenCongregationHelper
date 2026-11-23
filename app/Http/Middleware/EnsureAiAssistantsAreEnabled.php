<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AiAssistantConnections;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Let the AI assistants reach the MCP server (and register to it) only while the super administrators allow them (see
 * the Security settings), and only for the users who can still log in.
 *
 * The setting is read on each request rather than when the routes are registered, so that a change applies right away,
 * even with cached routes.
 */
class EnsureAiAssistantsAreEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AiAssistantConnections::enabled(), 404);

        $user = $request->user();

        abort_if($user instanceof User && ! $user->can_login, 403, 'This account cannot log in.');

        return $next($request);
    }
}
