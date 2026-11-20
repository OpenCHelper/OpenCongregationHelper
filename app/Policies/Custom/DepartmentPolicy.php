<?php

declare(strict_types=1);

namespace App\Policies\Custom;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * The departments are defined by the application: they can't be created or deleted, only their members can be
 * changed (`update`), by the administrators and by the overseers of each department.
 *
 * Written by hand, so it lives outside `App\Policies` (registered in `AppServiceProvider`): `shield:generate` skips the
 * models whose policy is registered from elsewhere, instead of replacing it with one checking the permissions only.
 */
class DepartmentPolicy
{
    use HandlesAuthorization;

    /**
     * The overseers can open the list of the departments, to reach their own.
     */
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Department')
            || ($authUser instanceof User && $authUser->isDepartmentOverseer());
    }

    public function view(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('View:Department') || $this->isOverseer($authUser, $department);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Department $department): bool
    {
        return $authUser->can('Update:Department') || $this->isOverseer($authUser, $department);
    }

    public function delete(AuthUser $authUser, Department $department): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    private function isOverseer(AuthUser $authUser, Department $department): bool
    {
        return $authUser instanceof User && $department->isOverseer($authUser);
    }
}
