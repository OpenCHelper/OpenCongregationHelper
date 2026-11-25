<?php

use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    $this->rootUser = User::factory()->create(['id' => 1]);

    $this->admin = User::factory()->create();
    foreach (['ViewAny:User', 'View:User', 'Update:User', 'Delete:User', 'DeleteAny:User'] as $permission) {
        $this->admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $this->publisher = User::factory()->create(['can_login' => true]);
});

/**
 * Assign a new territory to the given user, optionally already returned or deleted.
 */
function assignTerritoryTo(User $assignee, bool $returned = false, bool $deleted = false): TerritoryAssignment
{
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => $returned ? now()->subDay() : null,
    ]);

    if ($deleted) {
        $assignment->delete();
    }

    return $assignment;
}

test('a user who has assignments cannot be deleted', function (bool $returned, bool $deleted) {
    $assignment = assignTerritoryTo($this->publisher, $returned, $deleted);

    expect($this->publisher->delete())->toBeFalse()
        ->and(User::find($this->publisher->id))->not->toBeNull()
        ->and(TerritoryAssignment::withTrashed()->find($assignment->id))->not->toBeNull();
})->with([
    'in progress' => [false, false],
    'returned' => [true, false],
    'deleted' => [true, true],
]);

test('a user without assignments can be deleted', function () {
    expect($this->publisher->delete())->toBeTrue()
        ->and(User::find($this->publisher->id))->toBeNull();
});

test('the database refuses to delete a user who has assignments', function () {
    $assignment = assignTerritoryTo($this->publisher, returned: true);

    expect(fn () => DB::table('users')->where('id', $this->publisher->id)->delete())->toThrow(QueryException::class)
        ->and(TerritoryAssignment::find($assignment->id))->not->toBeNull();
});

test('deleting a user who has assignments from their page is refused with an explanation', function (string $page) {
    assignTerritoryTo($this->publisher);
    $this->actingAs($this->admin);

    Livewire::test($page, ['record' => $this->publisher->getKey()])
        ->mountAction('delete')
        ->assertActionMounted('delete')
        ->assertMountedActionModalSee(__('filament-resources.user.delete_refused_body'))
        ->assertMountedActionModalSee(__('filament-resources.user.action_disable_access'))
        ->callMountedAction()
        ->assertNotified(__('filament-resources.user.delete_refused_title'))
        ->assertNoRedirect();

    expect(User::find($this->publisher->id))->not->toBeNull();
})->with([
    'edit page' => EditUser::class,
    'view page' => ViewUser::class,
]);

test('a user without assignments can be deleted from their page', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->publisher->getKey()])
        ->callAction('delete')
        ->assertRedirect();

    expect(User::find($this->publisher->id))->toBeNull();
});

test('the access of a user who has assignments can be disabled in place of deleting them', function () {
    $assignment = assignTerritoryTo($this->publisher);
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->publisher->getKey()])
        ->callAction([TestAction::make('delete'), TestAction::make('disableAccess')])
        ->assertNotified(__('filament-resources.user.access_disabled', ['name' => $this->publisher->name]))
        ->assertSchemaStateSet(['can_login' => false]);

    expect($this->publisher->fresh()->isVirtual())->toBeTrue()
        ->and($assignment->fresh())->not->toBeNull();
});

test('a virtual user who has assignments is not offered to disable their access', function () {
    $this->publisher->update(['can_login' => false]);
    assignTerritoryTo($this->publisher);
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->publisher->getKey()])
        ->mountAction('delete')
        ->assertMountedActionModalSee(__('filament-resources.user.delete_refused_body'))
        ->assertMountedActionModalDontSee(__('filament-resources.user.action_disable_access'));
});

test('bulk deleting users skips the root user and the ones who have assignments, and reports them', function () {
    assignTerritoryTo($this->publisher, returned: true);
    $freeUser = User::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$this->rootUser, $this->publisher, $freeUser])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified(
            FilamentNotification::make()
                ->warning()
                ->title(trans_choice('filament-actions::delete.multiple.notifications.deleted_partial.title', 1, ['count' => 1, 'total' => 3]))
                ->body(
                    '<p>'.__('profile.root_user_protection.account_deletion_not_allowed').'</p>'
                    .'<p>'.trans_choice('filament-resources.user.delete_refused_bulk', 1).'</p>'
                )
                ->persistent(),
        );

    expect(User::find($this->rootUser->id))->not->toBeNull()
        ->and(User::find($this->publisher->id))->not->toBeNull()
        ->and(User::find($freeUser->id))->toBeNull();
});

test('a refused deletion keeps the roles and permissions of the user', function (bool $isRootUser) {
    $user = $isRootUser ? $this->rootUser : $this->publisher;
    $user->assignRole(Role::findOrCreate('publisher', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('View:Territory', 'web'));

    if (! $isRootUser) {
        assignTerritoryTo($user, returned: true);
    }

    try {
        $user->delete();
    } catch (Exception) {
        // The root user cannot be deleted: the exception explains it
    }

    $user = User::find($user->id);

    expect($user)->not->toBeNull()
        ->and($user->roles()->pluck('name')->all())->toBe(['publisher'])
        ->and($user->permissions()->pluck('name')->all())->toBe(['View:Territory']);
})->with([
    'user who has assignments' => false,
    'root user' => true,
]);

test('bulk deleting users keeps the roles and permissions of the ones skipped', function () {
    $this->publisher->assignRole(Role::findOrCreate('publisher', 'web'));
    $this->publisher->givePermissionTo(Permission::findOrCreate('View:Territory', 'web'));
    assignTerritoryTo($this->publisher, returned: true);
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$this->publisher])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect($this->publisher->roles()->count())->toBe(1)
        ->and($this->publisher->permissions()->count())->toBe(1);
});

test('bulk deleting users goes on with the others when the deletion of one fails', function () {
    Exceptions::fake();
    $failingUser = User::factory()->create();
    $freeUser = User::factory()->create();
    $this->actingAs($this->admin);

    User::deleting(function (User $user) use ($failingUser): void {
        if ($user->is($failingUser)) {
            throw new RuntimeException('The database refused the deletion.');
        }
    });

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$failingUser, $freeUser])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified(trans_choice('filament-actions::delete.multiple.notifications.deleted_partial.title', 1, ['count' => 1, 'total' => 2]));

    expect(User::find($failingUser->id))->not->toBeNull()
        ->and(User::find($freeUser->id))->toBeNull();
    Exceptions::assertReported(RuntimeException::class);
});

test('users who have assignments cannot delete their own account', function () {
    assignTerritoryTo($this->publisher, returned: true);
    $this->actingAs($this->publisher);

    Livewire::test(DeleteUserForm::class)
        ->assertSee(__('profile.delete_account.has_assignments'))
        ->assertDontSeeHtml('wire:click="confirmUserDeletion"')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password'])
        ->assertNoRedirect();

    expect(User::find($this->publisher->id))->not->toBeNull();
    $this->assertAuthenticatedAs($this->publisher);
});

test('the account deletion refuses users who have assignments', function () {
    assignTerritoryTo($this->publisher);

    expect(fn () => (new DeleteUser(new DeleteTeam))->delete($this->publisher))->toThrow(ValidationException::class)
        ->and(User::find($this->publisher->id))->not->toBeNull();
});
