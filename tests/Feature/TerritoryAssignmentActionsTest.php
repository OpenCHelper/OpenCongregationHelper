<?php

use App\Filament\Resources\Campaigns\Pages\ManageCampaignAssignments;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
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

/**
 * @param  array<int, string>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create(['can_login' => true]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create();
    $this->territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();
});

test('users allowed to create assignments can assign a territory', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.database_notifications.assignment_success_title'));

    expect($this->territory->currentAssignment()->first()?->assignee_id)->toBe($this->assignee->id);
});

test('assigning a territory to a terminated campaign is refused', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    $terminatedCampaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDay(),
    ]);

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
            'campaign_id' => $terminatedCampaign->id,
        ])
        ->assertHasFormErrors(['campaign_id']);

    expect($this->territory->assignments()->count())->toBe(0);
});

test('a failure notification is shown when the assignment cannot be saved', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    TerritoryAssignment::creating(function (): void {
        throw new RuntimeException('Simulated database failure');
    });

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
        ])
        ->assertNotified(__('filament-resources.database_notifications.assignment_error_title'));

    expect($this->territory->assignments()->count())->toBe(0);
});

test('assignment actions are hidden for users without assignment permissions', function () {
    $assignedTerritory = Territory::factory()->for($this->territory->territoryZone, 'territoryZone')->create();
    TerritoryAssignment::factory()->create([
        'territory_id' => $assignedTerritory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory']));

    Livewire::test(ListTerritories::class)
        ->assertActionHidden(TestAction::make('create_assignment')->table($this->territory))
        ->assertActionHidden(TestAction::make('cancel_assignment')->table($assignedTerritory))
        ->assertActionHidden(TestAction::make('create_assignment_bulk')->table()->bulk())
        ->assertActionHidden(TestAction::make('cancel_assignment_bulk')->table()->bulk());
});

test('users allowed to update assignments can return a territory', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Update:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->assertActionVisible(TestAction::make('cancel_assignment')->table($this->territory))
        ->callAction(TestAction::make('cancel_assignment')->table($this->territory))
        ->assertNotified(__('filament-resources.database_notifications.territory_returned_success_title'));

    expect($assignment->refresh()->ended_at)->not->toBeNull();
});

test('users that can list assignments see everyone assignments', function () {
    $otherAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment']));

    expect(TerritoryAssignmentResource::canViewAllAssignments())->toBeTrue()
        ->and(TerritoryAssignmentResource::getEloquentQuery()->pluck('id')->all())->toContain($otherAssignment->id);
});

test('users that cannot list assignments only see their own', function () {
    $user = userWithPermissions(['View:TerritoryAssignment']);

    $ownAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $user->id,
    ]);
    $otherAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
    ]);

    $this->actingAs($user);

    expect(TerritoryAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($ownAssignment->id)
        ->not->toContain($otherAssignment->id);
});

test('campaign assignments cannot be created for an already assigned territory', function () {
    $campaign = Campaign::factory()->create(['valid_until' => now()->addMonth()]);
    TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions([
        'View:Campaign', 'ViewAny:Campaign', 'Update:Campaign',
        'ViewAny:TerritoryAssignment', 'Create:TerritoryAssignment',
    ]));

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('create')->table(), [
            'territory_id' => $this->territory->id,
            'assignee_id' => $this->assignee->id,
            'started_at' => now()->toDateString(),
        ])
        ->assertHasFormErrors(['territory_id']);

    expect($this->territory->assignments()->count())->toBe(1);
});
