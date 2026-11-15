<?php

use App\Filament\Resources\TerritoryZones\Pages\EditTerritoryZone;
use App\Filament\Resources\TerritoryZones\Pages\ListTerritoryZones;
use App\Filament\Resources\TerritoryZones\Pages\ViewTerritoryZone;
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
        'ViewAny:TerritoryZone', 'View:TerritoryZone', 'Update:TerritoryZone',
        'Delete:TerritoryZone', 'DeleteAny:TerritoryZone', 'Restore:TerritoryZone', 'RestoreAny:TerritoryZone',
        'ForceDelete:TerritoryZone', 'ForceDeleteAny:TerritoryZone',
    ] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->zone = TerritoryZone::factory()->create();
    $this->territory = Territory::factory()->for($this->zone, 'territoryZone')->create();
});

test('a zone that still has territories cannot be deleted', function () {
    expect($this->zone->delete())->toBeFalse()
        ->and($this->zone->forceDelete())->toBeFalse()
        ->and(TerritoryZone::find($this->zone->id)?->trashed())->toBeFalse()
        ->and(Territory::find($this->territory->id))->not->toBeNull();
});

test('a zone whose territories have all been deleted can be deleted', function () {
    $this->territory->delete();

    expect($this->zone->delete())->toBeTrue()
        ->and(TerritoryZone::withTrashed()->find($this->zone->id)->trashed())->toBeTrue();
});

test('deleting a zone that still has territories from its pages is refused with an explanation', function (string $page) {
    Livewire::test($page, ['record' => $this->zone->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('filament-resources.territory_zone.delete_refused_title'))
        ->assertNoRedirect();

    expect(TerritoryZone::find($this->zone->id))->not->toBeNull();
})->with([
    'view page' => ViewTerritoryZone::class,
    'edit page' => EditTerritoryZone::class,
]);

test('bulk deleting zones skips the ones that still have territories and reports them', function () {
    $emptyZone = TerritoryZone::factory()->create();

    Livewire::test(ListTerritoryZones::class)
        ->selectTableRecords([$this->zone, $emptyZone])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified(
            FilamentNotification::make()
                ->warning()
                ->title(trans_choice('filament-actions::delete.multiple.notifications.deleted_partial.title', 1, ['count' => 1, 'total' => 2]))
                ->body('<p>'.trans_choice('filament-resources.territory_zone.delete_refused_bulk', 1).'</p>')
                ->persistent(),
        );

    expect(TerritoryZone::find($this->zone->id))->not->toBeNull()
        ->and(TerritoryZone::find($emptyZone->id))->toBeNull();
});

test('an empty zone can be deleted and then restored from the zone list', function () {
    $emptyZone = TerritoryZone::factory()->create();

    Livewire::test(EditTerritoryZone::class, ['record' => $emptyZone->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('filament-actions::delete.single.notifications.deleted.title'));

    expect(TerritoryZone::find($emptyZone->id))->toBeNull();

    Livewire::test(ListTerritoryZones::class)
        ->assertCanNotSeeTableRecords([$emptyZone])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$emptyZone])
        ->assertCanNotSeeTableRecords([$this->zone])
        ->callAction(TestAction::make('restore')->table($emptyZone))
        ->assertNotified(__('filament-actions::restore.single.notifications.restored.title'));

    expect(TerritoryZone::find($emptyZone->id))->not->toBeNull();
});

test('restoring a zone deleted with its territories brings the territories back', function () {
    // A zone deleted while it still had territories, before deleting such zones was refused
    $this->zone->deleteQuietly();

    expect(Territory::find($this->territory->id))->toBeNull();

    Livewire::test(ListTerritoryZones::class)
        ->filterTable('trashed', false)
        ->assertTableColumnStateSet('territories_count', 1, $this->zone)
        ->callAction(TestAction::make('restore')->table($this->zone));

    expect(TerritoryZone::find($this->zone->id))->not->toBeNull()
        ->and(Territory::find($this->territory->id))->not->toBeNull();
});

test('force deleting a deleted zone that still has territories is refused with an explanation', function () {
    $this->zone->deleteQuietly();

    Livewire::test(ListTerritoryZones::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make('forceDelete')->table($this->zone))
        ->assertNotified(__('filament-resources.territory_zone.delete_refused_title'));

    expect(TerritoryZone::withTrashed()->find($this->zone->id))->not->toBeNull()
        ->and(Territory::withoutGlobalScopes()->find($this->territory->id))->not->toBeNull();
});

test('a zone whose territories have all been deleted cannot be force deleted, which would lose their history', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => User::factory()->create()->id,
        'started_at' => now()->subMonth(),
        'ended_at' => now()->subWeek(),
    ]);
    $this->territory->delete();

    expect($this->zone->forceDelete())->toBeFalse()
        ->and($this->zone->delete())->toBeTrue()
        ->and($this->zone->forceDelete())->toBeFalse()
        ->and(TerritoryZone::withTrashed()->find($this->zone->id))->not->toBeNull()
        ->and(Territory::onlyTrashed()->withoutGlobalScope('parentNotDeleted')->find($this->territory->id))->not->toBeNull()
        ->and(TerritoryAssignment::withTrashed()->find($assignment->id))->not->toBeNull();
});

test('a zone can be force deleted once its deleted territories have been force deleted', function () {
    $this->territory->delete();
    $this->territory->forceDelete();

    expect($this->zone->forceDelete())->toBeTrue()
        ->and(TerritoryZone::withTrashed()->find($this->zone->id))->toBeNull();
});

test('force deleting a zone whose territories have all been deleted is refused with an explanation', function () {
    $this->territory->delete();
    $this->zone->delete();

    Livewire::test(ListTerritoryZones::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make('forceDelete')->table($this->zone))
        ->assertNotified(
            FilamentNotification::make()
                ->danger()
                ->title(__('filament-resources.territory_zone.delete_refused_title'))
                ->body(__('filament-resources.territory_zone.force_delete_refused_body'))
                ->persistent(),
        );

    expect(TerritoryZone::withTrashed()->find($this->zone->id))->not->toBeNull()
        ->and(Territory::onlyTrashed()->withoutGlobalScope('parentNotDeleted')->find($this->territory->id))->not->toBeNull();
});

test('bulk force deleting zones skips the ones that still have deleted territories and reports them', function () {
    $this->territory->delete();
    $this->zone->delete();
    $emptyZone = TerritoryZone::factory()->create();
    $emptyZone->delete();

    Livewire::test(ListTerritoryZones::class)
        ->filterTable('trashed', false)
        ->selectTableRecords([$this->zone, $emptyZone])
        ->callAction(TestAction::make('forceDelete')->table()->bulk())
        ->assertNotified(
            FilamentNotification::make()
                ->warning()
                ->title(trans_choice('filament-actions::force-delete.multiple.notifications.deleted_partial.title', 1, ['count' => 1, 'total' => 2]))
                ->body('<p>'.trans_choice('filament-resources.territory_zone.force_delete_refused_bulk', 1).'</p>')
                ->persistent(),
        );

    expect(TerritoryZone::withTrashed()->find($this->zone->id))->not->toBeNull()
        ->and(TerritoryZone::withTrashed()->find($emptyZone->id))->toBeNull();
});
