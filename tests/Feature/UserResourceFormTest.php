<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->manager = User::factory()->create(['can_login' => true]);
    foreach (['ViewAny:User', 'View:User', 'Create:User', 'Update:User'] as $permission) {
        $this->manager->givePermissionTo(Permission::create(['name' => $permission]));
    }

    $this->superAdmin = User::factory()->create(['can_login' => true]);
    $this->superAdmin->assignRole($this->superAdminRole);

    $this->user = User::factory()->create([
        'can_login' => true,
        'password' => 'original-password',
    ]);
});

test('editing a user without typing a password keeps the current password', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->fillForm(['first_name' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->user->refresh();

    expect($this->user->first_name)->toBe('Updated')
        ->and(Hash::check('original-password', $this->user->password))->toBeTrue();
});

test('editing a user with a new password updates it', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->fillForm(['password' => 'new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-password', $this->user->refresh()->password))->toBeTrue();
});

test('a password is required when creating a user that can log in', function () {
    $this->actingAs($this->manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'can_login' => true,
            'password' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);
});

test('users that are not super administrators cannot assign roles', function () {
    $this->actingAs($this->manager);

    Livewire::test(EditUser::class, ['record' => $this->manager->id])
        ->assertFormFieldDisabled('roles')
        ->fillForm(['roles' => [$this->superAdminRole->id]])
        ->call('save');

    expect($this->manager->refresh()->hasRole('super_admin'))->toBeFalse();
});

test('super administrators can assign roles', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(EditUser::class, ['record' => $this->user->id])
        ->assertFormFieldEnabled('roles')
        ->fillForm(['roles' => [$this->editorRole->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->user->refresh()->hasRole('editor'))->toBeTrue();
});
