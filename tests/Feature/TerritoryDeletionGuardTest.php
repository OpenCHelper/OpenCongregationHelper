<?php

use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach ([
        'ViewAny:Territory', 'View:Territory', 'Update:Territory',
        'Delete:Territory', 'DeleteAny:Territory', 'ForceDelete:Territory', 'ForceDeleteAny:Territory',
        'ViewAny:TerritoryZone', 'View:TerritoryZone',
    ] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->assignee = User::factory()->create();
    $this->zone = TerritoryZone::factory()->create();
});

/**
 * Create a territory of the given zone, assigned (and optionally already returned) to the given user.
 */
function territoryAssignedTo(TerritoryZone $zone, User $assignee, bool $returned = false): Territory
{
    $territory = Territory::factory()->for($zone, 'territoryZone')->create();

    TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => $returned ? now()->subDay() : null,
    ]);

    return $territory;
}

test('a territory with an assignment in progress cannot be deleted', function () {
    $territory = territoryAssignedTo($this->zone, $this->assignee);

    expect($territory->delete())->toBeFalse()
        ->and($territory->forceDelete())->toBeFalse()
        ->and(Territory::find($territory->id)?->trashed())->toBeFalse()
        ->and($territory->currentAssignment()->exists())->toBeTrue();
});

test('a territory that is not assigned can be deleted', function (bool $hasReturnedAssignment) {
    $territory = $hasReturnedAssignment
        ? territoryAssignedTo($this->zone, $this->assignee, returned: true)
        : Territory::factory()->for($this->zone, 'territoryZone')->create();

    expect($territory->delete())->toBeTrue()
        ->and(Territory::withTrashed()->find($territory->id)->trashed())->toBeTrue();
})->with([
    'never assigned' => false,
    'assignment returned' => true,
]);

test('deleting an assigned territory from its edit page is refused with an explanation', function () {
    $territory = territoryAssignedTo($this->zone, $this->assignee);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('filament-resources.territory.delete_refused_title'))
        ->assertNoRedirect();

    expect(Territory::find($territory->id))->not->toBeNull();
});

test('deleting an assigned territory from the territories of its zone is refused with an explanation', function () {
    $territory = territoryAssignedTo($this->zone, $this->assignee);

    Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getKey()])
        ->callAction(TestAction::make('delete')->table($territory))
        ->assertNotified(__('filament-resources.territory.delete_refused_title'));

    expect(Territory::find($territory->id))->not->toBeNull();
});

test('bulk deleting territories skips the assigned ones and reports them', function (string $page) {
    $assignedTerritory = territoryAssignedTo($this->zone, $this->assignee);
    $freeTerritory = Territory::factory()->for($this->zone, 'territoryZone')->create();

    Livewire::test($page, $page === ManageZoneTerritories::class ? ['record' => $this->zone->getKey()] : [])
        ->selectTableRecords([$assignedTerritory, $freeTerritory])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified(
            FilamentNotification::make()
                ->warning()
                ->title(trans_choice('filament-actions::delete.multiple.notifications.deleted_partial.title', 1, ['count' => 1, 'total' => 2]))
                ->body('<p>'.trans_choice('filament-resources.territory.delete_refused_bulk', 1).'</p>')
                ->persistent(),
        );

    expect(Territory::find($assignedTerritory->id))->not->toBeNull()
        ->and(Territory::find($freeTerritory->id))->toBeNull();
})->with([
    'territories list' => ListTerritories::class,
    'zone territories' => ManageZoneTerritories::class,
]);

test('force deleting a deleted territory that is still assigned is refused with an explanation', function () {
    // A territory deleted while still assigned, before deleting assigned territories was refused
    $territory = territoryAssignedTo($this->zone, $this->assignee);
    $territory->deleteQuietly();

    Livewire::test(ListTerritories::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make('forceDelete')->table($territory))
        ->assertNotified(__('filament-resources.territory.delete_refused_title'));

    expect(Territory::withTrashed()->find($territory->id))->not->toBeNull()
        ->and(TerritoryAssignment::where('territory_id', $territory->id)->exists())->toBeTrue();
});
