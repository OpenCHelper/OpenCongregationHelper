<?php

namespace App\Support;

use App\Models\User;
use App\Settings\SecuritySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * The AI assistants (e.g. ChatGPT, Claude) connected to the MCP server of the app (see `routes/ai.php`).
 *
 * An assistant registers itself as an OAuth client (Dynamic Client Registration), then the user authorizes it: it is
 * connected while it has an access token not revoked, which it renews with its refresh token. Disconnecting it revokes
 * them, so that it must be authorized again.
 */
class AiAssistantConnections
{
    /**
     * Whether the super administrators allow the AI assistants (see the Security settings).
     */
    public static function enabled(): bool
    {
        return app(SecuritySettings::class)->ai_assistants_enabled;
    }

    /**
     * The address of the MCP server, to give to the assistants.
     */
    public static function serverUrl(): string
    {
        return route('mcp');
    }

    /**
     * The assistants the user connected, the last connected first.
     *
     * @return Collection<int, array{client_id: string, name: string, connected_at: Carbon}>
     */
    public static function forUser(User $user): Collection
    {
        return Passport::token()->newQuery()
            ->with('client')
            ->where('user_id', $user->getKey())
            ->where('revoked', false)
            // The refresh token outlives the access token: an assistant is still connected until it expires
            ->where(fn ($query) => $query->where('expires_at', '>', now())->orWhereHas('refreshToken', fn ($query) => $query
                ->where('revoked', false)
                ->where('expires_at', '>', now())))
            ->get()
            ->filter(fn (Token $token): bool => $token->client instanceof Client && ! $token->client->revoked)
            ->groupBy('client_id')
            ->map(fn (Collection $tokens): array => [
                'client_id' => (string) $tokens->first()->client_id,
                'name' => $tokens->first()->client->name,
                'connected_at' => $tokens->min('created_at'),
            ])
            ->sortByDesc('connected_at')
            ->values();
    }

    /**
     * Disconnect an assistant of the user.
     */
    public static function revoke(User $user, string $clientId): void
    {
        static::revokeTokens(Passport::token()->newQuery()->where('user_id', $user->getKey())->where('client_id', $clientId)->pluck('id'));

        Passport::authCode()->newQuery()
            ->where('user_id', $user->getKey())
            ->where('client_id', $clientId)
            ->update(['revoked' => true]);
    }

    /**
     * Disconnect the assistants of every user (e.g. when the super administrators no longer allow them).
     */
    public static function revokeAll(): void
    {
        static::revokeTokens(Passport::token()->newQuery()->where('revoked', false)->pluck('id'));

        Passport::authCode()->newQuery()->where('revoked', false)->update(['revoked' => true]);
    }

    /**
     * @param  Collection<int, string>  $accessTokenIds
     */
    private static function revokeTokens(Collection $accessTokenIds): void
    {
        foreach ($accessTokenIds->chunk(500) as $ids) {
            Passport::token()->newQuery()->whereKey($ids->all())->update(['revoked' => true]);
            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $ids->all())->update(['revoked' => true]);
        }
    }
}
