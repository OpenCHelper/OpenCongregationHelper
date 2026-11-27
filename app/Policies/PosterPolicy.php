<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Poster;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
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
        return false;
    }

    public function forceDelete(AuthUser $authUser, Poster $poster): bool
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

    public function replicate(AuthUser $authUser, Poster $poster): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
