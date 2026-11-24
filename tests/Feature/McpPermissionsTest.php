<?php

use App\Enums\ContactType;
use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Mcp\Servers\CongregationServer;
use App\Mcp\Tools\AssignTerritoryTool;
use App\Mcp\Tools\CreatePublicationRequestTool;
use App\Mcp\Tools\FetchTool;
use App\Mcp\Tools\GetMyAccountTool;
use App\Mcp\Tools\GetOverviewTool;
use App\Mcp\Tools\GetTerritoryTool;
use App\Mcp\Tools\ListAssignmentsTool;
use App\Mcp\Tools\ListCampaignsTool;
use App\Mcp\Tools\ListPeopleTool;
use App\Mcp\Tools\ListPublicationRequestsTool;
use App\Mcp\Tools\ListPublicationsTool;
use App\Mcp\Tools\ListTerritoriesTool;
use App\Mcp\Tools\ListTerritoryZonesTool;
use App\Mcp\Tools\RequestTerritoryReturnTool;
use App\Mcp\Tools\ReturnTerritoryTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\UpdatePublicationRequestStatusTool;
use App\Models\Contact;
use App\Models\Department;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Laravel\Mcp\Server\Tool;
use Spatie\Permission\Models\Permission;

/**
 * What each kind of user can see and change through the MCP server: the same as in the app, never more.
 */
beforeEach(function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);

    $this->overseer = User::factory()->create(['can_login' => true, 'first_name' => 'Olga', 'last_name' => 'Overseer']);
    Department::for(DepartmentKey::Territories)->members()->attach($this->overseer, ['role' => DepartmentRole::Overseer]);

    $this->publisher = User::factory()->create(['can_login' => true, 'first_name' => 'Paolo', 'last_name' => 'Publisher']);

    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);
    $this->territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma', 'notes' => 'Secret gate code 1234']);
    $this->othersAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->overseer->id,
        'started_at' => now()->subDays(10),
        'ended_at' => null,
        'notes' => 'Private note of the assignment',
    ]);
});

/**
 * A user who can log in, with the given permissions only.
 *
 * @param  list<string>  $permissions
 */
function mcpUserWith(array $permissions): User
{
    $user = User::factory()->create(['can_login' => true]);
    $user->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name), $permissions));

    return $user;
}

/**
 * The tools a user is offered.
 *
 * @return list<string>
 */
function mcpToolNamesFor(User $user): array
{
    test()->actingAs($user);

    return mcpTools()
        ->filter(fn (Tool $tool): bool => $tool->eligibleForRegistration())
        ->map(fn (Tool $tool): string => $tool->name())
        ->sort()
        ->values()
        ->all();
}

/**
 * Every tool of the server.
 *
 * @return Collection<int, Tool>
 */
function mcpTools(): Collection
{
    return collect((new ReflectionProperty(CongregationServer::class, 'tools'))->getDefaultValue())
        ->map(fn (string $class): Tool => app($class));
}

test('each user is offered only the tools their permissions allow', function (Closure $user, array $expected) {
    expect(mcpToolNamesFor($user()))->toBe(collect($expected)->sort()->values()->all());
})->with([
    'a publisher without permissions' => [
        fn () => test()->publisher,
        ['fetch', 'get_my_account', 'request_territory_return', 'search'],
    ],
    'who can only see the zones' => [
        fn () => mcpUserWith(['ViewAny:TerritoryZone', 'View:TerritoryZone']),
        ['fetch', 'get_my_account', 'list_territory_zones', 'request_territory_return', 'search'],
    ],
    'who can see the territories but not the assignments' => [
        fn () => mcpUserWith(['ViewAny:Territory', 'View:Territory']),
        ['fetch', 'get_my_account', 'get_territory', 'list_territories', 'request_territory_return', 'search'],
    ],
    'who can only record publication requests' => [
        fn () => mcpUserWith(['ViewAny:PublicationRequest', 'Create:PublicationRequest']),
        ['create_publication_request', 'fetch', 'get_my_account', 'list_people', 'list_publication_requests', 'list_publications', 'request_territory_return', 'search'],
    ],
]);

test('a tool that is not offered to the user cannot be called, and changes nothing', function (string $tool, Closure $arguments) {
    $arguments = $arguments();
    $requests = PublicationRequest::query()->count();

    CongregationServer::actingAs($this->publisher)
        ->tool($tool, $arguments)
        ->assertHasErrors();

    expect(TerritoryAssignment::query()->count())->toBe(1)
        ->and($this->othersAssignment->refresh()->ended_at)->toBeNull()
        ->and(PublicationRequest::query()->count())->toBe($requests)
        ->and(PublicationRequest::query()->whereNotNull('delivered_at')->exists())->toBeFalse();
})->with([
    'assign a territory' => [AssignTerritoryTool::class, fn () => ['territory_id' => Territory::factory()->for(test()->zone, 'territoryZone')->create()->id, 'assignee_id' => test()->publisher->id]],
    'return a territory' => [ReturnTerritoryTool::class, fn () => ['territory_id' => test()->territory->id]],
    'record a publication request' => [CreatePublicationRequestTool::class, fn () => ['publisher_id' => test()->publisher->id, 'publication_id' => Publication::factory()->create(['is_active' => true])->id]],
    'mark requests as delivered' => [UpdatePublicationRequestStatusTool::class, fn () => ['request_ids' => [PublicationRequest::factory()->create()->id], 'status' => 'delivered']],
    'list the territories' => [ListTerritoriesTool::class, fn () => []],
    'get a territory' => [GetTerritoryTool::class, fn () => ['territory_id' => test()->territory->id]],
    'list the assignments' => [ListAssignmentsTool::class, fn () => []],
    'list the people' => [ListPeopleTool::class, fn () => []],
    'list the campaigns' => [ListCampaignsTool::class, fn () => []],
    'list the zones' => [ListTerritoryZonesTool::class, fn () => []],
    'get the overview' => [GetOverviewTool::class, fn () => []],
    'list the publications' => [ListPublicationsTool::class, fn () => []],
    'list the publication requests' => [ListPublicationRequestsTool::class, fn () => []],
]);

test('the refused calls do not reveal the records', function () {
    CongregationServer::actingAs($this->publisher)
        ->tool(ReturnTerritoryTool::class, ['territory_id' => $this->territory->id])
        ->assertHasErrors()
        ->assertDontSee('Via Roma');
});

test('a publisher reads only their own records with search and fetch', function () {
    $mine = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Mia'])->id,
        'assignee_id' => $this->publisher->id,
        'started_at' => now()->subDay(),
        'ended_at' => null,
    ]);

    // Nothing of the congregation is found
    CongregationServer::actingAs($this->publisher)
        ->tool(SearchTool::class, ['query' => 'o'])
        ->assertOk()
        ->assertStructuredContentPath('results', []);

    foreach ([
        "territory:{$this->territory->id}",
        "zone:{$this->zone->id}",
        "assignment:{$this->othersAssignment->id}",
        "person:{$this->overseer->id}",
    ] as $id) {
        CongregationServer::actingAs($this->publisher)
            ->tool(FetchTool::class, ['id' => $id])
            ->assertHasErrors(['No '])
            ->assertDontSee(['Via Roma', 'Olga', 'Secret gate code', 'Private note']);
    }

    // Their own assignment and account are theirs
    CongregationServer::actingAs($this->publisher)
        ->tool(FetchTool::class, ['id' => "assignment:{$mine->id}"])
        ->assertOk()
        ->assertSee('Via Mia');

    CongregationServer::actingAs($this->publisher)
        ->tool(GetMyAccountTool::class)
        ->assertOk()
        ->assertSee('Via Mia')
        ->assertDontSee('Via Roma');
});

test('a publisher cannot ask to return the assignments of someone else', function () {
    CongregationServer::actingAs($this->publisher)
        ->tool(RequestTerritoryReturnTool::class, ['assignment_id' => $this->othersAssignment->id])
        ->assertHasErrors();

    expect($this->othersAssignment->refresh()->return_requested_at)->toBeNull();
});

test('who sees the territories but not the assignments only sees who has them, as in the app', function () {
    $viewer = mcpUserWith(['ViewAny:Territory', 'View:Territory']);

    CongregationServer::actingAs($viewer)
        ->tool(ListTerritoriesTool::class)
        ->assertOk()
        ->assertStructuredContentPath('items.0.current_assignment', ['assignee' => ['id' => $this->overseer->id, 'name' => 'Olga Overseer']])
        ->assertDontSee('Private note');

    CongregationServer::actingAs($viewer)
        ->tool(GetTerritoryTool::class, ['territory_id' => $this->territory->id])
        ->assertOk()
        ->assertStructuredContentMissingPath('latest_assignments')
        ->assertDontSee('Private note');

    CongregationServer::actingAs($viewer)
        ->tool(FetchTool::class, ['id' => "assignment:{$this->othersAssignment->id}"])
        ->assertHasErrors(['No assignment found']);
});

test('who sees only the zones does not see their territories', function () {
    $viewer = mcpUserWith(['ViewAny:TerritoryZone', 'View:TerritoryZone']);

    CongregationServer::actingAs($viewer)
        ->tool(FetchTool::class, ['id' => "zone:{$this->zone->id}"])
        ->assertOk()
        ->assertSee('North')
        ->assertDontSee(['Via Roma', 'Olga']);

    CongregationServer::actingAs($viewer)
        ->tool(SearchTool::class, ['query' => 'roma'])
        ->assertStructuredContentPath('results', []);
});

test('who records publication requests finds the people, but not how many territories they have', function () {
    $clerk = mcpUserWith(['ViewAny:PublicationRequest', 'Create:PublicationRequest']);

    CongregationServer::actingAs($clerk)
        ->tool(ListPeopleTool::class, ['search' => 'Olga'])
        ->assertOk()
        ->assertStructuredContentPath('items.0.name', 'Olga Overseer')
        ->assertStructuredContentMissingPath('items.0.assignments_in_progress_count');

    CongregationServer::actingAs($clerk)
        ->tool(FetchTool::class, ['id' => "person:{$this->overseer->id}"])
        ->assertOk()
        ->assertDontSee('Via Roma');
});

test('no tool shares the contacts of the people', function () {
    Contact::query()->create([
        'user_id' => $this->overseer->id,
        'type' => ContactType::PERSONAL,
        'name' => 'Home',
        'phone_number' => '+39 333 1234567',
        'address' => 'Via Segreta 7',
        'is_primary' => true,
    ]);

    foreach ([
        [ListPeopleTool::class, ['search' => 'Olga']],
        [FetchTool::class, ['id' => "person:{$this->overseer->id}"]],
        [ListAssignmentsTool::class, []],
        [ListTerritoriesTool::class, []],
    ] as [$tool, $arguments]) {
        CongregationServer::actingAs($this->overseer)
            ->tool($tool, $arguments)
            ->assertOk()
            ->assertDontSee(['+39 333 1234567', 'Via Segreta 7', $this->overseer->email]);
    }
});

test('no tool deletes anything', function () {
    $this->actingAs($this->overseer);

    $tools = mcpTools()->map(fn (Tool $tool): array => $tool->toArray());

    expect($tools->pluck('name')->filter(fn (string $name): bool => str_contains($name, 'delete')))->toBeEmpty()
        ->and($tools->filter(fn (array $tool): bool => ($tool['annotations']['destructiveHint'] ?? false) === true))->toBeEmpty();
});
