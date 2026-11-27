<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicationRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class PublicationRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicationRequest');
    }

    public function view(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return $authUser->can('View:PublicationRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicationRequest');
    }

    public function update(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return $authUser->can('Update:PublicationRequest');
    }

    public function delete(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return $authUser->can('Delete:PublicationRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PublicationRequest');
    }

    public function restore(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, PublicationRequest $publicationRequest): bool
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

    public function replicate(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
