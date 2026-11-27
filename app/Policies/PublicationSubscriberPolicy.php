<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicationSubscriber;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
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
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
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

    public function replicate(AuthUser $authUser, PublicationSubscriber $publicationSubscriber): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
