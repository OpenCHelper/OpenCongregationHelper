<?php

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
use App\Mcp\Tools\ListPeopleTool;
use App\Mcp\Tools\ListPublicationRequestsTool;
use App\Mcp\Tools\ListTerritoriesTool;
use App\Mcp\Tools\RequestTerritoryReturnTool;
use App\Mcp\Tools\ReturnTerritoryTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\UpdatePublicationRequestStatusTool;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\TerritoryReturnRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();

    // Fixed names, so that random ones (e.g. "Stroman") are never found by the searches of the tests
    User::factory()->create(['id' => 1, 'first_name' => 'Root', 'last_name' => 'User']);

    // The members of the territory department can do everything on the territories and their assignments
    $this->overseer = User::factory()->create(['can_login' => true, 'first_name' => 'Anna', 'last_name' => 'Bianchi']);
    Department::for(DepartmentKey::Territories)->members()->attach($this->overseer, ['role' => DepartmentRole::Overseer]);

    $this->publisher = User::factory()->create(['can_login' => true, 'first_name' => 'Mario', 'last_name' => 'Rossi']);

    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);
});

/**
 * Create a territory of the test zone.
 *
 * @param  array<string, mixed>  $attributes
 */
function mcpTerritory(array $attributes = []): Territory
{
    return Territory::factory()->for(test()->zone, 'territoryZone')->create($attributes);
}

/**
 * Assign a territory, in progress unless ended.
 *
 * @param  array<string, mixed>  $attributes
 */
function mcpAssignment(Territory $territory, User $assignee, array $attributes = []): TerritoryAssignment
{
    return TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => null,
        ...$attributes,
    ]);
}

test('a publisher without permissions is only offered the tools about their own account', function () {
    CongregationServer::actingAs($this->publisher)
        ->tools()
        ->assertRegistered([GetMyAccountTool::class, RequestTerritoryReturnTool::class, SearchTool::class, FetchTool::class])
        ->assertNotRegistered([ListTerritoriesTool::class, AssignTerritoryTool::class, ReturnTerritoryTool::class, ListPeopleTool::class, ListPublicationRequestsTool::class]);
});

test('the territory department is offered the tools to manage the territories', function () {
    CongregationServer::actingAs($this->overseer)
        ->tools()
        ->assertRegistered([GetOverviewTool::class, ListTerritoriesTool::class, GetTerritoryTool::class, ListAssignmentsTool::class, AssignTerritoryTool::class, ReturnTerritoryTool::class]);
});

test('the tools refuse the users without the permission even when called directly', function () {
    $territory = mcpTerritory();

    CongregationServer::actingAs($this->publisher)
        ->tool(AssignTerritoryTool::class, ['territory_id' => $territory->id, 'assignee_id' => $this->publisher->id])
        ->assertHasErrors();

    expect($territory->assignments()->exists())->toBeFalse();
});

test('my account lists my assignments in progress', function () {
    $assignment = mcpAssignment(mcpTerritory(['name' => 'Mine']), $this->publisher);
    mcpAssignment(mcpTerritory(['name' => 'Returned']), $this->publisher, ['ended_at' => now()]);

    CongregationServer::actingAs($this->publisher)
        ->tool(GetMyAccountTool::class)
        ->assertOk()
        ->assertStructuredContentPath('user.name', 'Mario Rossi')
        ->assertStructuredContentCount(1, 'my_assignments_in_progress')
        ->assertStructuredContentPath('my_assignments_in_progress.0.id', $assignment->id)
        ->assertStructuredContentPath('my_assignments_in_progress.0.can_request_return', true);
});

test('the territories can be filtered by availability and by the last time they were worked', function () {
    $assigned = mcpTerritory(['name' => 'Assigned']);
    mcpAssignment($assigned, $this->publisher);

    $recent = mcpTerritory(['name' => 'Worked recently']);
    mcpAssignment($recent, $this->publisher, ['started_at' => now()->subDays(20), 'ended_at' => now()->subDays(5)]);

    $old = mcpTerritory(['name' => 'Worked long ago']);
    mcpAssignment($old, $this->publisher, ['started_at' => now()->subYear(), 'ended_at' => now()->subMonths(10)]);

    $never = mcpTerritory(['name' => 'Never worked']);

    CongregationServer::actingAs($this->overseer)
        ->tool(ListTerritoriesTool::class, ['status' => 'assigned'])
        ->assertOk()
        ->assertStructuredContentCount(1, 'items')
        ->assertStructuredContentPath('items.0.id', $assigned->id)
        ->assertStructuredContentPath('items.0.current_assignment.assignee.name', 'Mario Rossi');

    CongregationServer::actingAs($this->overseer)
        ->tool(ListTerritoriesTool::class, ['status' => 'available', 'not_worked_since' => now()->subMonths(6)->toDateString(), 'sort' => 'last_returned_at'])
        ->assertOk()
        ->assertStructuredContentPath('total', 2)
        ->assertStructuredContentPath('items.0.id', $never->id)
        ->assertStructuredContentPath('items.1.id', $old->id)
        ->assertStructuredContentPath('items.1.last_returned_at', now()->subMonths(10)->toDateString());
});

test('the lists are paginated', function () {
    foreach (range(1, 3) as $index) {
        mcpTerritory(['name' => "Territory {$index}"]);
    }

    CongregationServer::actingAs($this->overseer)
        ->tool(ListTerritoriesTool::class, ['limit' => 2, 'page' => 2])
        ->assertOk()
        ->assertStructuredContentPaths(['total' => 3, 'page' => 2, 'has_more' => false])
        ->assertStructuredContentCount(1, 'items');
});

test('a territory shows its details and its latest assignments', function () {
    $territory = mcpTerritory(['name' => 'Center', 'notes' => 'Ring twice']);
    mcpAssignment($territory, $this->publisher, ['started_at' => now()->subDays(40), 'ended_at' => now()->subDays(30)]);

    CongregationServer::actingAs($this->overseer)
        ->tool(GetTerritoryTool::class, ['territory_id' => $territory->id])
        ->assertOk()
        ->assertStructuredContentPaths([
            'name' => 'Center',
            'zone.name' => 'North',
            'notes' => 'Ring twice',
            'status' => 'available',
            'average_days_to_work' => 10,
        ])
        ->assertStructuredContentCount(1, 'latest_assignments');

    CongregationServer::actingAs($this->overseer)
        ->tool(GetTerritoryTool::class, ['territory_id' => 999999])
        ->assertHasErrors(['No territory found']);
});

test('a territory can be assigned, but not twice', function () {
    $territory = mcpTerritory(['name' => 'To assign']);

    CongregationServer::actingAs($this->overseer)
        ->tool(AssignTerritoryTool::class, ['territory_id' => $territory->id, 'assignee_id' => $this->publisher->id])
        ->assertOk()
        ->assertStructuredContentPath('assignment.status', 'in_progress')
        ->assertStructuredContentPath('assignment.assignee.id', $this->publisher->id);

    $assignment = $territory->assignments()->sole();
    expect($assignment->created_by)->toBe($this->overseer->id)
        ->and($assignment->ended_at)->toBeNull();

    CongregationServer::actingAs($this->overseer)
        ->tool(AssignTerritoryTool::class, ['territory_id' => $territory->id, 'assignee_id' => $this->overseer->id])
        ->assertHasErrors([__('validation.territory_assignment.already_assigned')]);

    expect($territory->assignments()->count())->toBe(1);
});

test('a territory cannot be assigned for a campaign already ended', function () {
    $territory = mcpTerritory();
    $campaign = Campaign::factory()->create(['valid_from' => now()->subMonths(2), 'valid_until' => now()->subDay()]);

    CongregationServer::actingAs($this->overseer)
        ->tool(AssignTerritoryTool::class, ['territory_id' => $territory->id, 'assignee_id' => $this->publisher->id, 'campaign_id' => $campaign->id])
        ->assertHasErrors([__('validation.campaign.not_terminated')]);
});

test('a territory can be returned, with notes', function () {
    $territory = mcpTerritory(['name' => 'To return']);
    $assignment = mcpAssignment($territory, $this->publisher, ['notes' => null]);

    CongregationServer::actingAs($this->overseer)
        ->tool(ReturnTerritoryTool::class, ['territory_id' => $territory->id, 'returned_at' => now()->subDay()->toDateString(), 'notes' => 'Street 3 not worked'])
        ->assertOk()
        ->assertStructuredContentPath('assignment.status', 'returned')
        ->assertStructuredContentPath('assignment.ended_at', now()->subDay()->toDateString());

    expect($assignment->refresh())
        ->ended_at->toDateString()->toBe(now()->subDay()->toDateString())
        ->notes->toBe('Street 3 not worked');

    CongregationServer::actingAs($this->overseer)
        ->tool(ReturnTerritoryTool::class, ['territory_id' => $territory->id])
        ->assertHasErrors(['is not assigned']);
});

test('a territory cannot be returned before it was assigned', function () {
    $territory = mcpTerritory();
    $assignment = mcpAssignment($territory, $this->publisher, ['started_at' => now()->subDays(2)]);

    CongregationServer::actingAs($this->overseer)
        ->tool(ReturnTerritoryTool::class, ['territory_id' => $territory->id, 'returned_at' => now()->subDays(5)->toDateString()])
        ->assertHasErrors();

    expect($assignment->refresh()->ended_at)->toBeNull();
});

test('the assignees can ask to return their own assignments only', function () {
    $assignment = mcpAssignment(mcpTerritory(), $this->publisher);
    $someoneElses = mcpAssignment(mcpTerritory(), $this->overseer);

    CongregationServer::actingAs($this->publisher)
        ->tool(RequestTerritoryReturnTool::class, ['assignment_id' => $someoneElses->id])
        ->assertHasErrors();

    CongregationServer::actingAs($this->publisher)
        ->tool(RequestTerritoryReturnTool::class, ['assignment_id' => $assignment->id])
        ->assertOk()
        ->assertStructuredContentPath('assignment.status', 'return_requested');

    expect($assignment->refresh()->return_requested_at)->not->toBeNull()
        ->and($someoneElses->refresh()->return_requested_at)->toBeNull();
    Notification::assertSentTo($this->overseer, TerritoryReturnRequestedNotification::class);

    CongregationServer::actingAs($this->publisher)
        ->tool(RequestTerritoryReturnTool::class, ['assignment_id' => $assignment->id])
        ->assertHasErrors(['already requested']);
});

test('the assignments can be filtered by status and person', function () {
    $campaign = Campaign::factory()->create(['valid_from' => now()->subMonths(2), 'valid_until' => now()->subDay()]);
    // Assigned while the campaign was active
    $overdue = TerritoryAssignment::withoutEvents(fn () => mcpAssignment(mcpTerritory(), $this->publisher, ['campaign_id' => $campaign->id]));
    mcpAssignment(mcpTerritory(), $this->overseer);
    mcpAssignment(mcpTerritory(), $this->publisher, ['ended_at' => now()]);

    CongregationServer::actingAs($this->overseer)
        ->tool(ListAssignmentsTool::class, ['status' => 'overdue'])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('items.0.id', $overdue->id)
        ->assertStructuredContentPath('items.0.status', 'overdue');

    CongregationServer::actingAs($this->overseer)
        ->tool(ListAssignmentsTool::class, ['assignee_id' => $this->publisher->id, 'status' => 'in_progress'])
        ->assertOk()
        ->assertStructuredContentPath('total', 1);
});

test('the overview counts what needs attention', function () {
    mcpAssignment(mcpTerritory(), $this->publisher, ['started_at' => now()->subYear()]);
    mcpTerritory();

    CongregationServer::actingAs($this->overseer)
        ->tool(GetOverviewTool::class)
        ->assertOk()
        ->assertStructuredContentPaths([
            'territories.total' => 2,
            'territories.available' => 1,
            'territories.assigned' => 1,
            'assignments.in_progress' => 1,
            'assignments.out_for_more_than_60_days' => 1,
        ]);
});

test('people are found by name, without their contacts', function () {
    CongregationServer::actingAs($this->overseer)
        ->tool(ListPeopleTool::class, ['search' => 'mario ross'])
        ->assertOk()
        ->assertStructuredContentPath('total', 1)
        ->assertStructuredContentPath('items.0.name', 'Mario Rossi')
        ->assertDontSee($this->publisher->email);
});

test('the publication requests can be recorded and marked as ordered and delivered', function () {
    $manager = User::factory()->create(['can_login' => true]);
    $manager->givePermissionTo(collect(['ViewAny:PublicationRequest', 'Create:PublicationRequest', 'Update:PublicationRequest', 'ViewAny:User'])
        ->map(fn (string $name): Permission => Permission::findOrCreate($name)));

    $publication = Publication::factory()->create(['is_active' => true]);
    $inactive = Publication::factory()->create(['is_active' => false]);

    CongregationServer::actingAs($manager)
        ->tool(CreatePublicationRequestTool::class, ['publisher_id' => $this->publisher->id, 'publication_id' => $inactive->id])
        ->assertHasErrors(['no longer offered']);

    CongregationServer::actingAs($manager)
        ->tool(CreatePublicationRequestTool::class, ['publisher_id' => $this->publisher->id, 'publication_id' => $publication->id, 'quantity' => 2])
        ->assertOk()
        ->assertStructuredContentPath('request.status', 'requested');

    $request = PublicationRequest::query()->sole();
    expect($request)->quantity->toBe(2)->created_by->toBe($manager->id);

    CongregationServer::actingAs($manager)
        ->tool(ListPublicationRequestsTool::class, ['status' => 'to_order'])
        ->assertStructuredContentPath('total', 1);

    CongregationServer::actingAs($manager)
        ->tool(UpdatePublicationRequestStatusTool::class, ['request_ids' => [$request->id, 999999], 'status' => 'ordered'])
        ->assertOk()
        ->assertStructuredContentPath('not_found_ids', [999999])
        ->assertStructuredContentPath('requests.0.status', 'ordered');

    CongregationServer::actingAs($manager)
        ->tool(UpdatePublicationRequestStatusTool::class, ['request_ids' => [$request->id], 'status' => 'delivered'])
        ->assertStructuredContentPath('requests.0.status', 'delivered');

    expect($request->refresh()->delivered_at)->not->toBeNull();
});

test('the search finds the records the user can see, to read with fetch', function () {
    $territory = mcpTerritory(['name' => 'Via Roma']);
    mcpTerritory(['name' => 'Piazza Garibaldi']);
    mcpAssignment($territory, $this->publisher);

    CongregationServer::actingAs($this->overseer)
        ->tool(SearchTool::class, ['query' => 'roma'])
        ->assertOk()
        ->assertStructuredContentCount(1, 'results')
        ->assertStructuredContentPath('results.0.id', "territory:{$territory->id}")
        ->assertStructuredContentPath('results.0.title', __('ai-assistants.kinds.territory').': Via Roma');

    CongregationServer::actingAs($this->overseer)
        ->tool(SearchTool::class, ['query' => 'Mario Rossi'])
        ->assertStructuredContentPath('results.0.id', "person:{$this->publisher->id}");

    $fetched = CongregationServer::actingAs($this->overseer)
        ->tool(FetchTool::class, ['id' => "territory:{$territory->id}"])
        ->assertOk()
        ->assertStructuredContentPaths(['id' => "territory:{$territory->id}", 'title' => __('ai-assistants.kinds.territory').': Via Roma', 'metadata.kind' => 'territory'])
        ->assertSee('Mario Rossi');

    CongregationServer::actingAs($this->overseer)
        ->tool(FetchTool::class, ['id' => "person:{$this->publisher->id}"])
        ->assertOk()
        ->assertSee('Via Roma')
        ->assertDontSee($this->publisher->email);
});

test('the search and fetch only show what the user can see', function () {
    $territory = mcpTerritory(['name' => 'Via Roma']);
    $mine = mcpAssignment($territory, $this->publisher);
    $someoneElses = mcpAssignment(mcpTerritory(['name' => 'Via Milano']), $this->overseer);

    CongregationServer::actingAs($this->publisher)
        ->tool(SearchTool::class, ['query' => 'via'])
        ->assertOk()
        ->assertStructuredContentPath('results', []);

    CongregationServer::actingAs($this->publisher)
        ->tool(FetchTool::class, ['id' => "territory:{$territory->id}"])
        ->assertHasErrors(['No territory found']);

    CongregationServer::actingAs($this->publisher)
        ->tool(FetchTool::class, ['id' => "assignment:{$someoneElses->id}"])
        ->assertHasErrors(['No assignment found']);

    // Their own assignments and account are theirs to read
    CongregationServer::actingAs($this->publisher)
        ->tool(FetchTool::class, ['id' => "assignment:{$mine->id}"])
        ->assertOk()
        ->assertSee('Via Roma');

    CongregationServer::actingAs($this->publisher)
        ->tool(FetchTool::class, ['id' => "person:{$this->publisher->id}"])
        ->assertOk();

    CongregationServer::actingAs($this->publisher)
        ->tool(FetchTool::class, ['id' => 'nonsense'])
        ->assertHasErrors(['Unknown id']);
});

test('the zones and campaigns can be fetched, and the overview lists the active campaigns', function () {
    $territory = mcpTerritory(['name' => 'Via Roma']);
    $campaign = Campaign::factory()->create(['name' => 'Memorial', 'valid_from' => now()->subWeek(), 'valid_until' => now()->addMonth()]);
    mcpAssignment($territory, $this->publisher, ['campaign_id' => $campaign->id]);

    CongregationServer::actingAs($this->overseer)
        ->tool(FetchTool::class, ['id' => "zone:{$this->zone->id}"])
        ->assertOk()
        ->assertSee(['North', 'Via Roma']);

    CongregationServer::actingAs($this->overseer)
        ->tool(FetchTool::class, ['id' => "campaign:{$campaign->id}"])
        ->assertOk()
        ->assertSee(['Memorial', 'Via Roma', 'assignments_in_progress_count']);

    CongregationServer::actingAs($this->overseer)
        ->tool(GetOverviewTool::class)
        ->assertOk()
        ->assertStructuredContentPath('active_campaigns.0.name', 'Memorial');
});
