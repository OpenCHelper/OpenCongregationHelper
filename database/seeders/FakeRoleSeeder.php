<?php

namespace Database\Seeders;

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the roles of a congregation, besides `super_admin`, and give them to the fake users.
 *
 * The permission names are the ones checked by the policies (`Action:Model`, see `ShieldSeeder`).
 */
class FakeRoleSeeder extends Seeder
{
    /**
     * Every action of a resource.
     */
    private const ALL_ACTIONS = [
        'View', 'ViewAny', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny',
        'Replicate', 'Reorder', 'ForceDelete', 'ForceDeleteAny',
    ];

    private const READ_ACTIONS = ['View', 'ViewAny'];

    private const EDIT_ACTIONS = ['View', 'ViewAny', 'Create', 'Update'];

    /**
     * The permissions every role has.
     */
    private const COMMON_PERMISSIONS = ['View:TerritoryGlobalMap'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var class-string<Model> $roleModel */
        $roleModel = Utils::getRoleModel();
        /** @var class-string<Model> $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        foreach ($this->roles() as $name => $permissions) {
            $role = $roleModel::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            $role->syncPermissions(array_map(
                fn (string $permission) => $permissionModel::firstOrCreate(['name' => $permission, 'guard_name' => 'web']),
                $permissions,
            ));
        }

        $this->assignRoles();
    }

    /**
     * Get the roles to create, with their permissions.
     *
     * @return array<string, list<string>>
     */
    public function roles(): array
    {
        return [
            // Manages the territories, their zones and their assignments
            'territory_servant' => [
                ...$this->permissions(self::ALL_ACTIONS, ['Territory', 'TerritoryZone', 'TerritoryAssignment', 'Campaign', 'Tag']),
                ...$this->permissions(self::READ_ACTIONS, ['User']),
                ...self::COMMON_PERMISSIONS,
            ],
            // Organizes the campaigns and oversees the ministry
            'service_overseer' => [
                ...$this->permissions([...self::EDIT_ACTIONS, 'Delete'], ['Campaign']),
                ...$this->permissions(self::READ_ACTIONS, ['Territory', 'TerritoryZone', 'TerritoryAssignment', 'Tag', 'User', 'Activity']),
                ...self::COMMON_PERMISSIONS,
            ],
            // Assigns the territories to the publishers of the group
            'group_overseer' => [
                ...$this->permissions(self::EDIT_ACTIONS, ['TerritoryAssignment']),
                ...$this->permissions(self::READ_ACTIONS, ['Territory', 'TerritoryZone', 'Campaign', 'User']),
                ...self::COMMON_PERMISSIONS,
            ],
            // Consults the territories
            'publisher' => [
                ...$this->permissions(self::READ_ACTIONS, ['Territory', 'TerritoryZone', 'Campaign']),
                ...self::COMMON_PERMISSIONS,
            ],
        ];
    }

    /**
     * Build the permission names of the given actions on the given models.
     *
     * @param  list<string>  $actions
     * @param  list<string>  $models
     * @return list<string>
     */
    private function permissions(array $actions, array $models): array
    {
        $permissions = [];

        foreach ($models as $model) {
            foreach ($actions as $action) {
                $permissions[] = "{$action}:{$model}";
            }
        }

        return $permissions;
    }

    /**
     * Give the roles to the users that have none, except the root user (who is `super_admin`):
     * one service overseer, two territory servants, two group overseers, and publishers.
     */
    private function assignRoles(): void
    {
        $users = User::query()
            ->whereKeyNot(1)
            ->whereDoesntHave('roles')
            ->with('roles')
            ->orderBy('id')
            ->get();

        foreach ($users->values() as $index => $user) {
            $user->assignRole(match (true) {
                $index === 0 => 'service_overseer',
                $index <= 2 => 'territory_servant',
                $index <= 4 => 'group_overseer',
                default => 'publisher',
            });
        }
    }
}
