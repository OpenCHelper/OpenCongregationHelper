<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicationRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

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
        return $authUser->can('Restore:PublicationRequest');
    }

    public function forceDelete(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return $authUser->can('ForceDelete:PublicationRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PublicationRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PublicationRequest');
    }

    public function replicate(AuthUser $authUser, PublicationRequest $publicationRequest): bool
    {
        return $authUser->can('Replicate:PublicationRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PublicationRequest');
    }
}
