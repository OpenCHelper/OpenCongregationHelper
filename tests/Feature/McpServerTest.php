<?php

use App\Filament\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Livewire\ProfileAiAssistants;
use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\AiAssistantConnections;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\ImpersonateManager;

beforeEach(function () {
    usePassportTestKeys();

    User::factory()->create(['id' => 1]);

    $this->user = User::factory()->create(['can_login' => true, 'first_name' => 'Mario', 'last_name' => 'Rossi']);
});

/**
 * Allow (or not) the AI assistants in the Security settings.
 */
function allowAiAssistants(bool $enabled = true): void
{
    $settings = app(SecuritySettings::class);
    $settings->ai_assistants_enabled = $enabled;
    $settings->save();
}

/**
 * Send a JSON-RPC request to the MCP server, as an AI assistant.
 *
 * @param  array<string, mixed>  $params
 * @param  array<string, string>  $headers
 */
function mcpRequest(string $method, array $params = [], array $headers = []): TestResponse
{
    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], [
        'Accept' => 'application/json, text/event-stream',
        ...$headers,
    ]);
}

/**
 * Connect an assistant for the user, with a client and an access token as after the OAuth flow.
 */
function connectAssistant(User $user, string $name = 'Claude'): Client
{
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://claude.ai/api/mcp/auth_callback'], confidential: false);

    Passport::token()->forceFill([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ])->save();

    return $client;
}

/**
 * Connect an assistant for the user through the whole OAuth flow, as ChatGPT or Claude do: it registers itself, the user
 * approves it, and it exchanges the code for its tokens. The session of the user is then forgotten, as the next
 * requests of the assistant only carry its access token.
 *
 * @return array{client_id: string, access_token: string, refresh_token: string}
 */
function connectAssistantThroughOAuth(User $user, string $redirectUri = 'https://claude.ai/api/mcp/auth_callback'): array
{
    $clientId = test()->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirectUri]])
        ->assertCreated()
        ->json('client_id');

    $verifier = Str::random(64);
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_'),
        'code_challenge_method' => 'S256',
    ]);

    // A previous request with an access token left `api` as the default guard of this test (each real request starts anew)
    app('auth')->shouldUse('web');

    $consent = test()->actingAs($user, 'web')->get('/oauth/authorize?'.$query)->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);

    $location = test()->post(route('passport.authorizations.approve'), ['state' => '', 'client_id' => $clientId, 'auth_token' => $matches[1]])
        ->assertRedirect()
        ->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $callback);

    $tokens = test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json();

    app('auth')->forgetGuards();
    test()->flushSession();
    app('auth')->guard('web')->logout();

    return ['client_id' => (string) $clientId, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']];
}

/**
 * The names of the tools listed to an assistant with the given access token.
 *
 * @return list<string>
 */
function mcpToolNamesWithToken(string $accessToken): array
{
    app('auth')->forgetGuards();

    return collect(mcpRequest('tools/list', [], ['Authorization' => 'Bearer '.$accessToken])->assertOk()->json('result.tools'))
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
}

test('the AI assistants are not allowed by default', function () {
    expect(app(SecuritySettings::class)->ai_assistants_enabled)->toBeFalse();

    Passport::actingAs($this->user, ['mcp:use'], 'api');

    mcpRequest('tools/list')->assertNotFound();
    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
    $this->postJson('/oauth/register', ['redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertNotFound();
});

test('the AI assistants stay off until a super administrator turns them on', function () {
    $this->seed();

    expect(app(SecuritySettings::class)->ai_assistants_enabled)->toBeFalse();
});

test('an assistant without a token is told where to authorize', function () {
    allowAiAssistants();

    mcpRequest('initialize')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');

    expect(mcpRequest('initialize')->headers->get('WWW-Authenticate'))->toContain('/.well-known/oauth-protected-resource');

    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'))
        ->assertJsonPath('authorization_servers.0', url('/'));

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

test('the keys work when the hosting does not apply their permissions (e.g. Altervista)', function () {
    allowAiAssistants();

    $keys = dirname(Passport::keyPath('oauth-private.key'));
    chmod($keys.'/oauth-private.key', 0644);
    chmod($keys.'/oauth-public.key', 0644);

    try {
        mcpRequest('initialize')->assertUnauthorized();
    } finally {
        chmod($keys.'/oauth-private.key', 0600);
        chmod($keys.'/oauth-public.key', 0600);
    }
});

test('the assistants can register themselves as OAuth clients', function (string $redirectUri) {
    allowAiAssistants();

    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirectUri]])
        ->assertCreated()
        ->assertJsonPath('redirect_uris', [$redirectUri]);

    expect(Client::query()->sole()->name)->toBe('Claude');
})->with([
    'Claude' => 'https://claude.ai/api/mcp/auth_callback',
    'ChatGPT' => 'https://chatgpt.com/connector_platform_oauth_redirect',
    'Claude Code' => 'http://localhost:33418/callback',
    'Cursor' => 'cursor://anysphere.cursor-retrieval/oauth/callback',
]);

test('an authorized assistant lists the tools of the user', function () {
    allowAiAssistants();
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    mcpRequest('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Open Congregation Helper');

    $tools = collect(mcpRequest('tools/list')->assertOk()->json('result.tools'))->pluck('name');

    expect($tools)->toContain('get_my_account', 'request_territory_return')
        ->not->toContain('assign_territory', 'list_territories');

    mcpRequest('tools/call', ['name' => 'get_my_account', 'arguments' => (object) []])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.user.name', 'Mario Rossi');
});

test('the users who can no longer log in cannot use their assistants', function () {
    allowAiAssistants();
    $this->user->update(['can_login' => false]);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    mcpRequest('tools/list')->assertForbidden();
});

test('an assistant connects through the whole OAuth flow and calls the tools with its token', function () {
    allowAiAssistants();

    // The assistant registers itself
    $redirectUri = 'https://claude.ai/api/mcp/auth_callback';
    $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirectUri]])
        ->assertCreated()
        ->json('client_id');

    // The user, sent to the app, logs in and is asked to approve it
    $verifier = Str::random(64);
    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $this->get('/oauth/authorize?'.$query)->assertRedirect(route('login'));

    $consent = $this->actingAs($this->user)->get('/oauth/authorize?'.$query)
        ->assertOk()
        ->assertSee('Claude')
        ->assertSee('claude.ai')
        ->assertSee('Mario Rossi');

    preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);

    $location = $this->post(route('passport.authorizations.approve'), [
        'state' => '',
        'client_id' => $clientId,
        'auth_token' => $matches[1],
    ])->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith($redirectUri);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $callback);
    expect($callback['state'])->toBe('xyz');

    // The assistant exchanges the code for its tokens
    $tokens = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json();

    expect($tokens)->toHaveKeys(['access_token', 'refresh_token']);

    // The next request of the assistant comes without the session of the user
    $this->app['auth']->forgetGuards();
    $this->flushSession();

    mcpRequest('tools/call', ['name' => 'get_my_account', 'arguments' => (object) []], ['Authorization' => 'Bearer '.$tokens['access_token']])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.user.id', $this->user->id);

    expect(AiAssistantConnections::forUser($this->user))->toHaveCount(1)
        ->first()->name->toBe('Claude');

    // Once disconnected, the token no longer works
    AiAssistantConnections::revoke($this->user, $clientId);
    $this->app['auth']->forgetGuards();

    mcpRequest('tools/list', [], ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
});

test('an administrator impersonating a user cannot approve an assistant for them', function () {
    allowAiAssistants();

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], confidential: false);

    $admin = User::factory()->create(['can_login' => true]);
    $admin->assignRole(Role::findOrCreate('super_admin'));

    // As the impersonation of the panel: logged in as the user, with the administrator remembered in the session
    $this->actingAs($this->user)->withSession([ImpersonateManager::SESSION_KEY => $admin->id]);

    $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]))->assertForbidden();
});

test('the users see the address of the server and disconnect their assistants from the profile', function () {
    allowAiAssistants();
    $client = connectAssistant($this->user);
    $other = connectAssistant(User::factory()->create());

    $this->actingAs($this->user);

    Livewire::test(ProfileAiAssistants::class)
        ->assertSee(url('/mcp'))
        ->assertSee('Claude')
        ->call('disconnect', $client->id)
        ->assertSee(__('ai-assistants.profile.none'));

    expect(AiAssistantConnections::forUser($this->user))->toBeEmpty()
        ->and(Token::query()->where('client_id', $other->id)->value('revoked'))->toBeFalse();
});

test('the profile does not offer the assistants while they are not allowed', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfileAiAssistants::class)->assertDontSee(__('ai-assistants.profile.title'));
});

test('turning the assistants off disconnects all of them', function () {
    Filament::setCurrentPanel('app');
    allowAiAssistants();
    connectAssistant($this->user);

    $admin = User::factory()->create(['can_login' => true]);
    $admin->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($admin);

    Livewire::test(ManageSecuritySettings::class)
        ->fillForm(['ai_assistants_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SecuritySettings::class)->ai_assistants_enabled)->toBeFalse()
        ->and(Token::query()->where('revoked', false)->exists())->toBeFalse();
});

test('an assistant gets the permissions the user has through their roles', function () {
    allowAiAssistants();

    $role = Role::findOrCreate('territory_manager');
    $role->givePermissionTo([Permission::findOrCreate('ViewAny:Territory'), Permission::findOrCreate('View:Territory')]);
    $this->user->assignRole($role);

    expect(mcpToolNamesWithToken(connectAssistantThroughOAuth($this->user)['access_token']))->toContain('list_territories', 'get_territory');
});

test('an assistant gets the permissions given to the user directly', function () {
    allowAiAssistants();

    $this->user->givePermissionTo(Permission::findOrCreate('ViewAny:TerritoryZone'));

    expect(mcpToolNamesWithToken(connectAssistantThroughOAuth($this->user)['access_token']))->toContain('list_territory_zones');
});

test('the assistant of a user who can no longer log in stops working', function () {
    allowAiAssistants();
    $tokens = connectAssistantThroughOAuth($this->user);

    $this->user->update(['can_login' => false]);

    mcpRequest('tools/call', ['name' => 'get_my_account', 'arguments' => (object) []], ['Authorization' => 'Bearer '.$tokens['access_token']])
        ->assertForbidden();
});

test('an administrator impersonating a user cannot get a code for an assistant the user already approved', function () {
    allowAiAssistants();
    $redirectUri = 'http://localhost:33418/callback';
    $tokens = connectAssistantThroughOAuth($this->user, $redirectUri);

    $admin = User::factory()->create(['can_login' => true]);
    $admin->assignRole(Role::findOrCreate('super_admin'));

    // As the impersonation of the panel: logged in as the user, with the administrator remembered in the session
    $response = $this->actingAs($this->user)
        ->withSession([ImpersonateManager::SESSION_KEY => $admin->id])
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $tokens['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]));

    expect((string) $response->headers->get('Location'))->not->toContain('code=');
    $response->assertForbidden();
});
