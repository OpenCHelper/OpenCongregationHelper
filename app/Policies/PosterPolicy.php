<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Poster;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PosterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Poster');
    }

    public function view(AuthUser $authUser, Poster $poster): bool
    {
        return $authUser->can('View:Poster');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Poster');
    }

    public function update(AuthUser $authUser, Poster $poster): bool
    {
        return $authUser->can('Update:Poster');
    }

    public function delete(AuthUser $authUser, Poster $poster): bool
    {
        return $authUser->can('Delete:Poster');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Poster');
    }

    public function restore(AuthUser $authUser, Poster $poster): bool
    {
        return $authUser->can('Restore:Poster');
    }

    public function forceDelete(AuthUser $authUser, Poster $poster): bool
    {
        return $authUser->can('ForceDelete:Poster');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Poster');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Poster');
    }

    public function replicate(AuthUser $authUser, Poster $poster): bool
    {
        return $authUser->can('Replicate:Poster');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Poster');
    }
}
