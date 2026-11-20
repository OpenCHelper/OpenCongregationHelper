<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicationSubscriber;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PublicationSubscriberPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PublicationSubscriber');
    }

    public function view(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return $authUser->can('View:PublicationSubscriber');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PublicationSubscriber');
    }

    public function update(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return $authUser->can('Update:PublicationSubscriber');
    }

    public function delete(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return $authUser->can('Delete:PublicationSubscriber');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PublicationSubscriber');
    }

    public function restore(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return $authUser->can('Restore:PublicationSubscriber');
    }

    public function forceDelete(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return $authUser->can('ForceDelete:PublicationSubscriber');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PublicationSubscriber');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PublicationSubscriber');
    }

    public function replicate(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return $authUser->can('Replicate:PublicationSubscriber');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PublicationSubscriber');
    }
}
