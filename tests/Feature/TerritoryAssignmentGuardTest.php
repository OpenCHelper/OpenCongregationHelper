<?php

use App\Filament\Resources\Territories\Pages\ManageTerritoryAssignments as ManageAssignmentsOfTerritory;
use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
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
        ->assertHasFormErrors(['ended_at']);

    expect($returnedAssignment->refresh()->ended_at)->not->toBeNull();
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
