<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Territory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class TerritoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Territory');
    }

    public function view(AuthUser $authUser, Territory $territory): bool
    {
        return $authUser->can('View:Territory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Territory');
    }

    public function update(AuthUser $authUser, Territory $territory): bool
    {
        return $authUser->can('Update:Territory');
    }

    public function delete(AuthUser $authUser, Territory $territory): bool
    {
        return $authUser->can('Delete:Territory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Territory');
    }

    public function restore(AuthUser $authUser, Territory $territory): bool
    {
        return $authUser->can('Restore:Territory');
    }

    public function forceDelete(AuthUser $authUser, Territory $territory): bool
    {
        return $authUser->can('ForceDelete:Territory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Territory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Territory');
    }

    public function replicate(AuthUser $authUser, Territory $territory): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
