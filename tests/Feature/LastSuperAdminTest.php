<?php

use App\Actions\Jetstream\DeleteUser;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
    There must always be a super administrator who can log in, to manage the application: the last one cannot lose the
    role or the access, nor be deleted.
*/

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    app()->detectEnvironment(fn (): string => 'production');

    $this->superAdminRole = Role::findOrCreate('super_admin', 'web');

    // The root user without the role (e.g. it gave it away), so that the last super administrator can be deleted
    $this->rootUser = User::factory()->create(['id' => 1, 'can_login' => true]);
    $this->superAdmin = User::factory()->create(['can_login' => true, 'password' => 'admin-password']);
    $this->superAdmin->assignRole($this->superAdminRole);
});

test('the last super administrator who can log in is recognised', function () {
    expect($this->superAdmin->isLastSuperAdmin())->toBeTrue();

    // A virtual super administrator cannot manage the application
    tap(User::factory()->create(['can_login' => false]))->assignRole($this->superAdminRole);
    expect($this->superAdmin->fresh()->isLastSuperAdmin())->toBeTrue();

    tap(User::factory()->create(['can_login' => true]))->assignRole($this->superAdminRole);
    expect($this->superAdmin->fresh()->isLastSuperAdmin())->toBeFalse();
});

test('the access of the last super administrator cannot be disabled', function () {
    expect(fn () => $this->superAdmin->update(['can_login' => false]))->toThrow(ValidationException::class)
        ->and($this->superAdmin->fresh()->can_login)->toBeTrue();
});

test('the access of a super administrator can be disabled while another one can log in', function () {
    tap(User::factory()->create(['can_login' => true]))->assignRole($this->superAdminRole);

    $this->superAdmin->update(['can_login' => false]);

    expect($this->superAdmin->fresh()->can_login)->toBeFalse();
});

test('the user form refuses to remove the access or the role of the last super administrator', function (string $field) {
    $this->actingAs($this->superAdmin);

    Livewire::test(EditUser::class, ['record' => $this->superAdmin->getKey()])
        ->set("data.{$field}", $field === 'can_login' ? false : [])
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect($this->superAdmin->fresh()->can_login)->toBeTrue()
        ->and($this->superAdmin->fresh()->hasRole('super_admin'))->toBeTrue();
})->with(['can_login', 'roles']);

test('the last super administrator cannot be deleted', function () {
    $otherAdmin = tap(User::factory()->create(['can_login' => true]))->givePermissionTo(
        collect(['ViewAny:User', 'View:User', 'Delete:User', 'DeleteAny:User'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web'))
    );

    expect($this->superAdmin->delete())->toBeFalse()
        ->and(User::find($this->superAdmin->id))->not->toBeNull();

    $this->actingAs($otherAdmin);

    Livewire::test(ViewUser::class, ['record' => $this->superAdmin->getKey()])
        ->mountAction('delete')
        ->assertMountedActionModalSee(__('filament-resources.user.last_super_admin'));

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$this->superAdmin])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(User::find($this->superAdmin->id))->not->toBeNull();
});

test('the last super administrator cannot delete their own account', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(DeleteUserForm::class)
        ->assertSee(__('profile.delete_account.last_super_admin'))
        ->assertDontSeeHtml('wire:click="confirmUserDeletion"');

    expect(fn () => (new DeleteUser)->delete($this->superAdmin))->toThrow(ValidationException::class)
        ->and(User::find($this->superAdmin->id))->not->toBeNull();
});

test('the super admin role cannot be deleted', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListRoles::class)
        ->assertActionHidden(TestAction::make('delete')->table($this->superAdminRole));

    Livewire::test(EditRole::class, ['record' => $this->superAdminRole->getKey()])
        ->assertActionHidden('delete');

    expect($this->superAdminRole->delete())->toBeFalse()
        ->and(Role::findByName('super_admin', 'web'))->not->toBeNull();
});
