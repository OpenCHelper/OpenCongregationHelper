<?php

use Database\Seeders\ShieldSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('the shield seeder uses the permission names checked by the policies', function () {
    $this->seed(ShieldSeeder::class);

    $superAdminPermissions = Role::findByName('super_admin', 'web')->permissions->pluck('name');

    expect($superAdminPermissions)
        ->toContain('ViewAny:TerritoryAssignment', 'Create:Campaign', 'Update:User', 'View:TerritoryGlobalMap')
        ->and(Permission::where('name', 'like', '%::%')->exists())->toBeFalse();
});
