<?php

use App\Filament\Resources\Territories\Pages\ManageTerritoryAssignments as ManageAssignmentsOfTerritory;
use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Rules\NotTerminatedCampaign;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create();
    $this->territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    $this->assign = fn (array $attributes = []): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
        ...$attributes,
    ]);

    $this->terminatedCampaign = fn (): Campaign => Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDay(),
    ]);

    // Assignments are linked to a campaign while it is running: the campaign ends afterwards
    $this->assignToEndedCampaign = function (): TerritoryAssignment {
        $campaign = Campaign::factory()->create(['valid_from' => now()->subDays(10), 'valid_until' => now()->addDay()]);
        $assignment = ($this->assign)(['campaign_id' => $campaign->id]);
        $campaign->update(['valid_until' => now()->subDay()]);

        return $assignment;
    };
});

test('a territory cannot have two assignments in progress', function () {
    ($this->assign)();

    expect(fn () => ($this->assign)())->toThrow(ValidationException::class);
    expect($this->territory->assignments()->count())->toBe(1);
});

test('returned assignments can be recorded while another one is in progress', function () {
    ($this->assign)();

    ($this->assign)(['started_at' => now()->subMonths(2), 'ended_at' => now()->subMonth()]);

    expect($this->territory->assignments()->count())->toBe(2);
});

test('a returned assignment cannot be reopened while another one is in progress', function () {
    $returnedAssignment = ($this->assign)(['ended_at' => now()->subDay()]);
    ($this->assign)();

    expect(fn () => $returnedAssignment->update(['ended_at' => null]))->toThrow(ValidationException::class);
    expect($returnedAssignment->refresh()->ended_at)->not->toBeNull();
});

test('a deleted assignment in progress cannot be restored while another one is in progress', function () {
    $deletedAssignment = ($this->assign)();
    $deletedAssignment->delete();
    ($this->assign)();

    expect(fn () => $deletedAssignment->restore())->toThrow(ValidationException::class);
    expect($deletedAssignment->refresh()->trashed())->toBeTrue();
});

test('an assignment cannot be linked to a terminated campaign', function () {
    $terminatedCampaign = ($this->terminatedCampaign)();

    expect(fn () => ($this->assign)(['campaign_id' => $terminatedCampaign->id]))->toThrow(ValidationException::class);
    expect($this->territory->assignments()->count())->toBe(0);
});

test('an assignment cannot be moved to a terminated campaign', function () {
    $campaign = Campaign::factory()->create();
    $assignment = ($this->assign)(['campaign_id' => $campaign->id]);

    expect(fn () => $assignment->update(['campaign_id' => ($this->terminatedCampaign)()->id]))->toThrow(ValidationException::class);
    expect($assignment->refresh()->campaign_id)->toBe($campaign->id);
});

test('an assignment can be returned after its campaign has ended', function () {
    $assignment = ($this->assignToEndedCampaign)();
    $campaignId = $assignment->campaign_id;

    $assignment->update(['ended_at' => now()]);

    expect($assignment->refresh())
        ->campaign_id->toBe($campaignId)
        ->ended_at->not->toBeNull();
});

test('the campaign rule accepts the terminated campaign the assignment is already linked to', function () {
    $linkedCampaign = ($this->terminatedCampaign)();
    $otherCampaign = ($this->terminatedCampaign)();
    $rules = ['campaign_id' => new NotTerminatedCampaign(ignoredCampaignId: $linkedCampaign->id)];

    expect(Validator::make(['campaign_id' => $linkedCampaign->id], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['campaign_id' => $otherCampaign->id], $rules)->passes())->toBeFalse();
});

test('the edit form shows an error when reopening a returned assignment of an assigned territory', function () {
    $user = User::factory()->create();
    foreach (['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $returnedAssignment = ($this->assign)(['ended_at' => now()->subDay()]);
    ($this->assign)();

    Livewire::test(ManageTerritoryAssignments::class)
        ->filterTable('ended_at', null)
        ->callAction(TestAction::make('edit')->table($returnedAssignment), ['ended_at' => null])
        ->assertHasFormErrors(['ended_at'])
        ->assertHasNoFormErrors(['territory_id']);

    expect($returnedAssignment->refresh()->ended_at)->not->toBeNull();
});

test('an assignment in progress can be edited', function () {
    $user = User::factory()->create();
    foreach (['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $assignment = ($this->assign)();

    Livewire::test(ManageTerritoryAssignments::class)
        ->callAction(TestAction::make('edit')->table($assignment), ['notes' => 'Second floor done'])
        ->assertHasNoFormErrors();

    expect($assignment->refresh())
        ->notes->toBe('Second floor done')
        ->territory_id->toBe($this->territory->id)
        ->ended_at->toBeNull();
});

test('an assignment of a terminated campaign can be returned from the edit form', function () {
    $user = User::factory()->create();
    foreach (['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $assignment = ($this->assignToEndedCampaign)();
    $terminatedCampaign = $assignment->campaign;
    $returnedAt = now()->startOfMinute();

    Livewire::test(ManageTerritoryAssignments::class)
        ->mountAction(TestAction::make('edit')->table($assignment))
        ->assertFormFieldExists('campaign_id', 'mountedActionSchema0', fn (Select $field): bool => $field->getOptionLabel() === $terminatedCampaign->name)
        ->fillForm(['ended_at' => $returnedAt])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($assignment->refresh())
        ->campaign_id->toBe($terminatedCampaign->id)
        ->ended_at->toEqual($returnedAt);
});

test('a new assignment cannot be linked to a terminated campaign from the form', function () {
    $user = User::factory()->create();
    foreach (['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Create:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $terminatedCampaign = ($this->terminatedCampaign)();

    Livewire::test(ManageTerritoryAssignments::class)
        ->mountAction('create')
        ->assertFormFieldExists('campaign_id', 'mountedActionSchema0', fn (Select $field): bool => ! array_key_exists($terminatedCampaign->id, $field->getOptions()))
        ->fillForm([
            'territory_id' => $this->territory->id,
            'assignee_id' => $this->assignee->id,
            'campaign_id' => $terminatedCampaign->id,
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['campaign_id']);

    expect($this->territory->assignments()->count())->toBe(0);
});

test('the assignments of a territory can only be listed by users allowed to list all assignments', function () {
    $user = User::factory()->create();
    foreach (['ViewAny:Territory', 'View:Territory'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    expect(ManageAssignmentsOfTerritory::canAccess(['record' => $this->territory]))->toBeFalse();

    $user->givePermissionTo(Permission::findOrCreate('ViewAny:TerritoryAssignment', 'web'));

    expect(ManageAssignmentsOfTerritory::canAccess(['record' => $this->territory]))->toBeTrue();
});

test('only territories that are not assigned can be picked for a new assignment', function () {
    $user = User::factory()->create();
    foreach (['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Create:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    ($this->assign)();
    $unassignedTerritory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    Livewire::test(ManageTerritoryAssignments::class)
        ->mountAction('create')
        ->assertFormFieldExists('territory_id', 'mountedActionSchema0', fn (Select $field): bool => array_key_exists($unassignedTerritory->id, $field->getOptions())
            && ! array_key_exists($this->territory->id, $field->getOptions()));
});
