<?php

use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
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

    $this->user = User::factory()->create();
    $this->assignee = User::factory()->create();
});

test('new zones, territories and assignments remember the user who created them', function () {
    $this->actingAs($this->user);

    $zone = TerritoryZone::factory()->create();
    $territory = Territory::factory()->for($zone, 'territoryZone')->create();
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
        'created_by' => null,
    ]);

    expect($zone->refresh()->created_by)->toBe($this->user->id)
        ->and($territory->refresh()->created_by)->toBe($this->user->id)
        ->and($assignment->refresh()->created_by)->toBe($this->user->id);
});

test('the creator given explicitly is kept', function () {
    $this->actingAs($this->user);

    $zone = TerritoryZone::factory()->create(['created_by' => $this->assignee->id]);
    $territory = Territory::factory()->for($zone, 'territoryZone')->create(['created_by' => $this->assignee->id]);

    expect($zone->refresh()->created_by)->toBe($this->assignee->id)
        ->and($territory->refresh()->created_by)->toBe($this->assignee->id);
});

test('records created without a logged in user have no creator', function () {
    $zone = TerritoryZone::factory()->create();
    $territory = Territory::factory()->for($zone, 'territoryZone')->create();

    expect($zone->refresh()->created_by)->toBeNull()
        ->and($territory->refresh()->created_by)->toBeNull();
});

test('an assignment created from the form remembers the user who created it', function () {
    foreach (['ViewAny:TerritoryAssignment', 'Create:TerritoryAssignment'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);

    $territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    Livewire::test(ManageTerritoryAssignments::class)
        ->callAction(TestAction::make('create'), [
            'territory_id' => $territory->id,
            'assignee_id' => $this->assignee->id,
        ])
        ->assertHasNoFormErrors();

    expect(TerritoryAssignment::sole()->created_by)->toBe($this->user->id);
});
