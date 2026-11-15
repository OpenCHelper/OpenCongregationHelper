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
