<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["view_role","view_any_role","create_role","update_role","delete_role","delete_any_role","view_territory","view_any_territory","create_territory","update_territory","restore_territory","restore_any_territory","replicate_territory","reorder_territory","delete_territory","delete_any_territory","force_delete_territory","force_delete_any_territory","view_territory::assignment","view_any_territory::assignment","create_territory::assignment","update_territory::assignment","restore_territory::assignment","restore_any_territory::assignment","replicate_territory::assignment","reorder_territory::assignment","delete_territory::assignment","delete_any_territory::assignment","force_delete_territory::assignment","force_delete_any_territory::assignment","view_territory::assignment::type","view_any_territory::assignment::type","create_territory::assignment::type","update_territory::assignment::type","restore_territory::assignment::type","restore_any_territory::assignment::type","replicate_territory::assignment::type","reorder_territory::assignment::type","delete_territory::assignment::type","delete_any_territory::assignment::type","force_delete_territory::assignment::type","force_delete_any_territory::assignment::type","view_territory::zone","view_any_territory::zone","create_territory::zone","update_territory::zone","restore_territory::zone","restore_any_territory::zone","replicate_territory::zone","reorder_territory::zone","delete_territory::zone","delete_any_territory::zone","force_delete_territory::zone","force_delete_any_territory::zone","view_user","view_any_user","create_user","update_user","restore_user","restore_any_user","replicate_user","reorder_user","delete_user","delete_any_user","force_delete_user","force_delete_any_user","page_ApiTokens","page_EditProfile","page_Logs","widget_TerritoryGlobalMap"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name' => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
