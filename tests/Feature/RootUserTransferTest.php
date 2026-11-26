<?php

use App\Filament\Resources\Users\Actions\TransferRootAction;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Settings\SecuritySettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
    The root user is the first user until it hands its role over to another super administrator who can log in: only
    the root user can do it, in production, after confirming its password.
*/

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    app()->detectEnvironment(fn (): string => 'production');

    $superAdminRole = Role::findOrCreate('super_admin', 'web');

    $this->rootUser = User::factory()->create(['id' => 1, 'can_login' => true, 'password' => 'root-password']);
    $this->rootUser->assignRole($superAdminRole);

    $this->otherSuperAdmin = User::factory()->create(['can_login' => true]);
    $this->otherSuperAdmin->assignRole($superAdminRole);
});

test('the root user is the first user by default', function () {
    expect(User::rootUserId())->toBe(1)
        ->and($this->rootUser->isRootUser())->toBeTrue()
        ->and(User::rootUser()->is($this->rootUser))->toBeTrue();
});

test('the root user can hand its role over to another super administrator', function () {
    $this->actingAs($this->rootUser);

    Livewire::test(ViewUser::class, ['record' => $this->otherSuperAdmin->getKey()])
        ->assertActionEnabled('transferRoot')
        ->mountAction('transferRoot')
        ->set('mountedActions.0.data.password', 'root-password')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.user.transfer_root_done', ['name' => $this->otherSuperAdmin->name]));

    expect(app(SecuritySettings::class)->root_user_id)->toBe($this->otherSuperAdmin->getKey())
        ->and($this->otherSuperAdmin->fresh()->isRootUser())->toBeTrue()
        ->and($this->rootUser->fresh()->isRootUser())->toBeFalse()
        // The protections follow the root user
        ->and($this->otherSuperAdmin->fresh()->canBeImpersonated())->toBeFalse()
        ->and($this->rootUser->fresh()->canBeImpersonated())->toBeTrue();
});

test('a wrong password does not hand the role over', function () {
    $this->actingAs($this->rootUser);

    Livewire::test(ViewUser::class, ['record' => $this->otherSuperAdmin->getKey()])
        ->mountAction('transferRoot')
        ->set('mountedActions.0.data.password', 'wrong-password')
        ->callMountedAction()
        ->assertHasFormErrors(['password']);

    expect($this->rootUser->fresh()->isRootUser())->toBeTrue();
});

test('only a super administrator who can log in can become the root user', function (string $who) {
    $user = match ($who) {
        'without the role' => User::factory()->create(['can_login' => true]),
        'virtual' => tap(User::factory()->create(['can_login' => false]))->assignRole('super_admin'),
    };
    $this->actingAs($this->rootUser);

    expect(TransferRootAction::getRefusal($user))->toBe(__('filament-resources.user.transfer_root_requirements'));

    Livewire::test(ViewUser::class, ['record' => $user->getKey()])
        ->assertActionDisabled('transferRoot');

    expect(fn () => $user->makeRootUser())->toThrow(LogicException::class)
        ->and($this->rootUser->fresh()->isRootUser())->toBeTrue();
})->with(['without the role', 'virtual']);

test('only the root user is offered to hand the role over', function () {
    $this->actingAs($this->otherSuperAdmin);

    Livewire::test(ViewUser::class, ['record' => $this->otherSuperAdmin->getKey()])
        ->assertActionHidden('transferRoot');
});

test('the root user cannot be changed outside of production', function () {
    app()->detectEnvironment(fn (): string => 'preview');
    $this->actingAs($this->rootUser);

    Livewire::test(ViewUser::class, ['record' => $this->otherSuperAdmin->getKey()])
        ->assertActionDisabled('transferRoot');
});

test('after the handover, the other super administrators can edit the former root user but not the new one', function () {
    $this->otherSuperAdmin->makeRootUser();
    $thirdSuperAdmin = tap(User::factory()->create(['can_login' => true]))->assignRole('super_admin');

    app()->detectEnvironment(fn (): string => 'preview');
    $this->actingAs($thirdSuperAdmin);

    expect(UserResource::canEdit($this->rootUser->fresh()))->toBeTrue()
        ->and(UserResource::canEdit($this->otherSuperAdmin->fresh()))->toBeFalse();
});

test('creating the root user again makes the first user the root user', function () {
    $this->otherSuperAdmin->makeRootUser();
    User::query()->delete();

    $root = User::createRoot(['first_name' => 'New', 'last_name' => 'Root', 'email' => 'root@example.com', 'can_login' => true]);

    expect($root->getKey())->toBe(1)
        ->and(app(SecuritySettings::class)->root_user_id)->toBe(1)
        ->and($root->isRootUser())->toBeTrue();
});
