<?php

namespace App\Observers;

use App\Models\User;
use Exception;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updating" event.
     * This is called BEFORE the model is updated.
     */
    public function updating(User $user): void
    {
        // Protect root user's critical fields in non-production environments
        if ($user->isRootUserProtected()) {
            $protectedFields = ['email', 'password', 'can_login'];

            foreach ($protectedFields as $field) {
                if ($user->isDirty($field)) {
                    // Restore the original value for protected fields
                    $user->$field = $user->getOriginal($field);
                }
            }
        }
    }

    /**
     * Handle the User "updated" event.
     *
     * The push subscriptions of users who can no longer log in are deleted: their devices may now belong to someone else,
     * and they could not enable the push notifications again anyway.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('can_login') && $user->isVirtual()) {
            $user->pushSubscriptions()->delete();
        }

        // Handle role protection after update
        if ($user->isRootUserProtected()) {
            // If roles were modified, we need to check if we should revert them
            // Since we can't easily detect role changes in the observer,
            // we'll handle this in the model methods themselves
        }
    }

    /**
     * Handle the User "deleting" event.
     * This is called BEFORE the model is deleted.
     */
    public function deleting(User $user): bool
    {
        // Prevent deletion of root user under any circumstances
        if ($user->isRootUser()) {
            throw new Exception(__('profile.root_user_protection.account_deletion_not_allowed'));
        }

        // The database removes the memberships without the checks of `DepartmentMember`
        if ($user->isLastDepartmentOverseer()) {
            throw new Exception(__('departments.users.delete_last_overseer'));
        }

        return true;
    }

    /**
     * Handle the User "deleted" event.
     *
     * The push subscriptions are linked polymorphically, so the database does not delete them with the user.
     */
    public function deleted(User $user): void
    {
        $user->pushSubscriptions()->delete();
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
