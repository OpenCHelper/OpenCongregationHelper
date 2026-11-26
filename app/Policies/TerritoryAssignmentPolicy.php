<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TerritoryAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class TerritoryAssignmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TerritoryAssignment');
    }

    /**
     * The assignees can always open their own assignments (e.g. from a notification), even without the permission.
     */
    public function view(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return $authUser->can('View:TerritoryAssignment')
            || $territoryAssignment->assignee_id === $authUser->getKey();
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TerritoryAssignment');
    }

    public function update(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return $authUser->can('Update:TerritoryAssignment');
    }

    /**
     * Only the assignee can ask the territory department to close their assignment (`TerritoryAssignment::requestReturn()`
     * tells whether it can still be asked).
     */
    public function requestReturn(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return $territoryAssignment->assignee_id === $authUser->getKey();
    }

    public function delete(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return $authUser->can('Delete:TerritoryAssignment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TerritoryAssignment');
    }

    public function restore(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return $authUser->can('Restore:TerritoryAssignment');
    }

    public function forceDelete(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return $authUser->can('ForceDelete:TerritoryAssignment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TerritoryAssignment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TerritoryAssignment');
    }

    public function replicate(AuthUser $authUser, TerritoryAssignment $territoryAssignment): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
