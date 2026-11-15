<?php

use App\Filament\Resources\Campaigns\Pages\ManageCampaignAssignments;
use App\Filament\Resources\Territories\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\Users\Pages\ManageUserAssignments;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create();
    $this->territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();
});

/**
 * @param  array<int, string>  $permissions
 */
function actingAsUserAllowedTo(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    test()->actingAs($user);

    return $user;
}

test('relation pages listing assignments require the permission to list all assignments', function (string $page, Closure $record) {
    actingAsUserAllowedTo([
        'View:Territory', 'ViewAny:Territory',
        'View:Campaign', 'ViewAny:Campaign',
        'View:User', 'ViewAny:User',
        'View:TerritoryAssignment',
    ]);

    Livewire::test($page, ['record' => $record->call($this)->getKey()])
        ->assertForbidden();
})->with([
    'territory assignments' => [ManageTerritoryAssignments::class, fn () => $this->territory],
    'campaign assignments' => [ManageCampaignAssignments::class, fn () => Campaign::factory()->create()],
    'user assignments' => [ManageUserAssignments::class, fn () => $this->assignee],
]);

test('assignments listed through a relationship are scoped to the current user', function () {
    $user = actingAsUserAllowedTo(['View:TerritoryAssignment']);

    $ownAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $user->id,
        'ended_at' => now()->subDay(),
    ]);
    $otherAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    expect(TerritoryAssignmentResource::scopeToVisibleAssignments($this->territory->assignments())->pluck('id')->all())
        ->toBe([$ownAssignment->id]);

    actingAsUserAllowedTo(['ViewAny:TerritoryAssignment']);

    expect(TerritoryAssignmentResource::scopeToVisibleAssignments($this->territory->assignments())->pluck('id')->all())
        ->toEqualCanonicalizing([$ownAssignment->id, $otherAssignment->id]);
});

test('the territory assignments page lists every assignment for users allowed to list them', function () {
    $assignments = TerritoryAssignment::factory()->count(2)->sequence(
        ['ended_at' => now()->subDays(2)],
        ['ended_at' => null],
    )->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(5),
    ]);

    actingAsUserAllowedTo(['View:Territory', 'ViewAny:Territory', 'ViewAny:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class, ['record' => $this->territory->id])
        // Show both the returned and the in progress assignments
        ->filterTable('ended_at', null)
        ->assertCanSeeTableRecords($assignments);
});

test('an assignment can be returned from the page of its terminated campaign', function () {
    $campaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->addDay(),
    ]);
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'campaign_id' => $campaign->id,
        'ended_at' => null,
    ]);
    $campaign->update(['valid_until' => now()->subDay()]);
    $returnedAt = now()->startOfMinute();

    actingAsUserAllowedTo(['View:Campaign', 'ViewAny:Campaign', 'ViewAny:TerritoryAssignment', 'Update:TerritoryAssignment']);

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('edit')->table($assignment), ['ended_at' => $returnedAt])
        ->assertHasNoFormErrors();

    expect($assignment->refresh())
        ->campaign_id->toBe($campaign->id)
        ->ended_at->toEqual($returnedAt);
});

test('an assignment cannot end before it starts from the page of its territory', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => null,
    ]);

    actingAsUserAllowedTo(['View:Territory', 'ViewAny:Territory', 'ViewAny:TerritoryAssignment', 'Update:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class, ['record' => $this->territory->id])
        ->callAction(TestAction::make('edit')->table($assignment), ['ended_at' => now()->subDays(20)])
        ->assertHasFormErrors(['ended_at' => ['after_or_equal', __('validation.territory_assignment.ended_before_started')]]);

    expect($assignment->refresh()->ended_at)->toBeNull();
});

test('an assignment cannot start in the future from the page of its campaign', function () {
    $campaign = Campaign::factory()->create(['valid_until' => now()->addMonth()]);

    actingAsUserAllowedTo(['View:Campaign', 'ViewAny:Campaign', 'ViewAny:TerritoryAssignment', 'Create:TerritoryAssignment']);

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('create')->table(), [
            'territory_id' => $this->territory->id,
            'assignee_id' => $this->assignee->id,
            'started_at' => now()->addDay()->toDateString(),
        ])
        ->assertHasFormErrors(['started_at' => ['before_or_equal', __('validation.territory_assignment.started_in_future')]]);

    expect($this->territory->assignments()->count())->toBe(0);
});

test('an assignment can be created from the page of its campaign', function () {
    $campaign = Campaign::factory()->create(['valid_until' => now()->addMonth()]);

    actingAsUserAllowedTo(['View:Campaign', 'ViewAny:Campaign', 'ViewAny:TerritoryAssignment', 'Create:TerritoryAssignment']);

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('create')->table(), [
            'territory_id' => $this->territory->id,
            'assignee_id' => $this->assignee->id,
            'started_at' => now()->toDateString(),
        ])
        ->assertHasNoFormErrors();

    expect($this->territory->assignments()->sole())
        ->campaign_id->toBe($campaign->id)
        ->started_at->isToday()->toBeTrue();
});
