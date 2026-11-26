<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    /**
     * While the root user is protected (outside production), only the root user itself may update it. The denial
     * carries a message, so that the Edit buttons can show it as their tooltip instead of disappearing.
     *
     * Customized: keep this method if the policy is generated again by Shield.
     */
    public function update(AuthUser $authUser, User $user): Response|bool
    {
        if (! $authUser->can('Update:User')) {
            return false;
        }

        return static::getRootUserProtectionResponse($authUser, $user) ?? true;
    }

    /**
     * The denial for updating the root user while it is protected, by anyone but the root user itself, or null when
     * the update is not prevented by the protection.
     *
     * Also checked by the user resource, because Shield allows everything to super administrators before the policy
     * is asked: to know whether someone can edit a user, use `UserResource::canEdit()`, not the gate.
     */
    public static function getRootUserProtectionResponse(?AuthUser $authUser, User $user): ?Response
    {
        if (! $user->isRootUserProtected() || ($authUser instanceof User && $authUser->isRootUser())) {
            return null;
        }

        return Response::deny(__('profile.root_user_protection.edit_action_disabled'));
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:User');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:User');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}
