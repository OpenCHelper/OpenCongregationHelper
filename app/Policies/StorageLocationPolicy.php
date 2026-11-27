<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StorageLocation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class StorageLocationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StorageLocation');
    }

    public function view(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return $authUser->can('View:StorageLocation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StorageLocation');
    }

    public function update(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return $authUser->can('Update:StorageLocation');
    }

    public function delete(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return $authUser->can('Delete:StorageLocation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
