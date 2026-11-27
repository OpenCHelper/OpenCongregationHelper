<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Publication;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class PublicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Publication');
    }

    public function view(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('View:Publication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Publication');
    }

    public function update(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('Update:Publication');
    }

    public function delete(AuthUser $authUser, Publication $publication): bool
    {
        return $authUser->can('Delete:Publication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Publication $publication): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Publication $publication): bool
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

    public function replicate(AuthUser $authUser, Publication $publication): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Publication');
    }
}
