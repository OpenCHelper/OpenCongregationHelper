<?php

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/*
 * Assignments in progress whose territory (or zone) was deleted before deleting
 * them was refused: their territory can no longer be loaded, but their pages must still open.
 */

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();

    User::factory()->create(['id' => 1]);

    $this->zone = TerritoryZone::factory()->create();
    $this->territory = Territory::factory()->for($this->zone, 'territoryZone')->withImage()->create(['name' => 'Lost territory']);

    $this->assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => User::factory()->create()->id,
        'ended_at' => null,
    ]);
});

dataset('deleted parents', [
    'deleted territory' => fn () => $this->territory,
    'deleted zone' => fn () => $this->zone,
]);

test('the page of an assignment whose territory was deleted opens', function (Territory|TerritoryZone $deletedParent) {
    Filament::setCurrentPanel('app');

    $user = User::factory()->create();
    foreach (['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $deletedParent->deleteQuietly();

    expect($this->assignment->fresh()->territory)->toBeNull();

    $this->actingAs($user)
        ->get(TerritoryAssignmentResource::getUrl('view', ['record' => $this->assignment]))
        ->assertOk()
        ->assertSee(__('filament-resources.territory_assignment.info_territory_placeholder'))
        ->assertDontSee('Lost territory');
})->with('deleted parents');

test('the shared page of an assignment whose territory was deleted opens', function (Territory|TerritoryZone $deletedParent) {
    $sharedUrl = $this->assignment->share();

    $deletedParent->deleteQuietly();

    $this->get('/guest/shared-assignment/'.$sharedUrl->uid)
        ->assertOk()
        ->assertSee(__('filament-resources.territory_assignment.info_territory_placeholder'));
})->with('deleted parents');
