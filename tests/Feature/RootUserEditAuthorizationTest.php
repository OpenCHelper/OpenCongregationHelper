<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    $this->rootUser = User::factory()->create(['id' => 1, 'can_login' => true]);
    $this->manager = User::factory()->create(['can_login' => true]);
    $this->publisher = User::factory()->create(['can_login' => true]);

    foreach (['ViewAny:User', 'View:User', 'Update:User'] as $permission) {
        Permission::create(['name' => $permission]);
        $this->rootUser->givePermissionTo($permission);
        $this->manager->givePermissionTo($permission);
    }
});

test('a user who can update users cannot open the edit page of the protected root user', function () {
    $this->actingAs($this->manager);

    $this->get(UserResource::getUrl('edit', ['record' => $this->rootUser]))->assertForbidden();
    Livewire::test(EditUser::class, ['record' => $this->rootUser->id])->assertForbidden();

    expect($this->manager->can('update', $this->rootUser))->toBeFalse()
        ->and($this->manager->can('update', $this->publisher))->toBeTrue();
});

test('the root user can still edit itself', function () {
    $this->actingAs($this->rootUser);

    $this->get(UserResource::getUrl('edit', ['record' => $this->rootUser]))->assertOk();

    Livewire::test(EditUser::class, ['record' => $this->rootUser->id])
        ->fillForm(['first_name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->rootUser->refresh()->first_name)->toBe('Renamed');
});

test('another super administrator cannot edit the protected root user either', function () {
    // Shield allows everything to super administrators before the policy is asked
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($superAdmin);

    $this->get(UserResource::getUrl('edit', ['record' => $this->rootUser]))->assertForbidden();
    Livewire::test(EditUser::class, ['record' => $this->rootUser->id])->assertForbidden();

    Livewire::test(ListUsers::class)
        ->assertActionDisabled(TestAction::make('edit')->table($this->rootUser))
        ->assertActionEnabled(TestAction::make('edit')->table($this->publisher))
        ->assertSeeHtml(Js::from(__('profile.root_user_protection.edit_action_disabled'))->toHtml());

    Livewire::test(ViewUser::class, ['record' => $this->rootUser->id])
        ->assertActionDisabled('edit');

    Livewire::test(EditUser::class, ['record' => $this->publisher->id])
        ->fillForm(['first_name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->publisher->refresh()->first_name)->toBe('Renamed');
});

test('the other users can edit the root user in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->actingAs($this->manager);

    expect($this->manager->can('update', $this->rootUser))->toBeTrue()
        ->and(UserResource::canEdit($this->rootUser))->toBeTrue();
});

test('a user without the permission cannot update anyone, the root user included', function (string $who) {
    expect($this->publisher->can('update', $who === 'root' ? $this->rootUser : $this->manager))->toBeFalse();
})->with(['root', 'other user']);

test('the edit buttons of the protected root user are disabled with an explanation for the other users', function () {
    $this->actingAs($this->manager);
    $message = __('profile.root_user_protection.edit_action_disabled');
    // The tooltip is rendered in the Alpine `x-tooltip` attribute, as a JavaScript string
    $tooltip = Js::from($message)->toHtml();

    expect(Gate::forUser($this->manager)->inspect('update', $this->rootUser)->message())->toBe($message);

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('edit')->table($this->rootUser))
        ->assertActionDisabled(TestAction::make('edit')->table($this->rootUser))
        ->assertActionEnabled(TestAction::make('edit')->table($this->publisher))
        ->assertSeeHtml($tooltip);

    Livewire::test(ViewUser::class, ['record' => $this->rootUser->id])
        ->assertActionVisible('edit')
        ->assertActionDisabled('edit')
        ->assertSeeHtml($tooltip);

    Livewire::test(ViewUser::class, ['record' => $this->publisher->id])
        ->assertActionEnabled('edit')
        ->assertDontSeeHtml($tooltip);
});

test('the edit buttons of the root user are enabled for the root user itself', function () {
    $this->actingAs($this->rootUser);

    Livewire::test(ListUsers::class)
        ->assertActionEnabled(TestAction::make('edit')->table($this->rootUser))
        ->assertDontSeeHtml(Js::from(__('profile.root_user_protection.edit_action_disabled'))->toHtml());

    Livewire::test(ViewUser::class, ['record' => $this->rootUser->id])
        ->assertActionEnabled('edit');
});

test('the edit buttons are hidden from the users who cannot update users', function () {
    $this->publisher->givePermissionTo(['ViewAny:User', 'View:User']);
    $this->actingAs($this->publisher);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('edit')->table($this->rootUser))
        ->assertActionHidden(TestAction::make('edit')->table($this->manager));

    Livewire::test(ViewUser::class, ['record' => $this->rootUser->id])
        ->assertActionHidden('edit');
});
