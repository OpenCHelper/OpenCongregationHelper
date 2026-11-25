<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    $this->rootUser = User::factory()->create(['id' => 1]);

    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo(Permission::create(['name' => 'ViewAny:User']));
    $this->actingAs($this->manager);
});

test('users can be sorted by role', function () {
    $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $viewerRole = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    // Users with several roles are sorted by the first one in alphabetical order
    $editorAndViewer = User::factory()->create();
    $editorAndViewer->assignRole($viewerRole, $editorRole);

    Livewire::test(ListUsers::class)
        ->sortTable('roles.name')
        ->assertCanSeeTableRecords([$admin, $editorAndViewer, $viewer], inOrder: true)
        ->sortTable('roles.name', 'desc')
        ->assertCanSeeTableRecords([$viewer, $editorAndViewer, $admin], inOrder: true);
});

test('users can be filtered by access, with the number of users of each option', function () {
    $userWithAccess = User::factory()->create();
    $virtualUsers = User::factory()->virtual()->count(2)->create();

    $component = Livewire::test(ListUsers::class);

    $filter = $component->instance()->getTable()->getFilter('can_login');
    expect($filter->getPlaceholder())->toBe(__('filament-resources.user.filter_access_all', ['count' => 5]))
        ->and($filter->getTrueLabel())->toBe(__('filament-resources.user.filter_access_with_access', ['count' => 3]))
        ->and($filter->getFalseLabel())->toBe(__('filament-resources.user.filter_access_virtual', ['count' => 2]));

    $component
        ->filterTable('can_login', true)
        ->assertCanSeeTableRecords([$this->manager, $userWithAccess])
        ->assertCanNotSeeTableRecords($virtualUsers)
        ->assertSee(__('filament-resources.user.filter_access_indicator_with_access'))
        ->filterTable('can_login', false)
        ->assertCanSeeTableRecords($virtualUsers)
        ->assertCanNotSeeTableRecords([$this->manager, $userWithAccess])
        ->assertSee(__('filament-resources.user.filter_access_indicator_virtual'));
});
