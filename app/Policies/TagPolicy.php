<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class TagPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Tag');
    }

    public function view(AuthUser $authUser, Tag $tag): bool
    {
        return $authUser->can('View:Tag');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Tag');
    }

    public function update(AuthUser $authUser, Tag $tag): bool
    {
        return $authUser->can('Update:Tag');
    }

    public function delete(AuthUser $authUser, Tag $tag): bool
    {
        return $authUser->can('Delete:Tag');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Tag');
    }

    public function restore(AuthUser $authUser, Tag $tag): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Tag $tag): bool
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

    public function replicate(AuthUser $authUser, Tag $tag): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
