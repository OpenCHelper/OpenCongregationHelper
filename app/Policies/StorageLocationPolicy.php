<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StorageLocation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

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
        return $authUser->can('DeleteAny:StorageLocation');
    }

    public function restore(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return $authUser->can('Restore:StorageLocation');
    }

    public function forceDelete(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return $authUser->can('ForceDelete:StorageLocation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StorageLocation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StorageLocation');
    }

    public function replicate(AuthUser $authUser, StorageLocation $storageLocation): bool
    {
        return $authUser->can('Replicate:StorageLocation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StorageLocation');
    }
}
