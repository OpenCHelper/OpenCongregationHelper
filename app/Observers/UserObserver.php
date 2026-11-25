<?php

namespace App\Observers;

use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Password;

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

        // Once the password is set another way ("Forgot password?", the profile page...), an unused invitation must
        // not be able to replace it
        if ($user->wasChanged('password')) {
            Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->deleteToken($user);
        }

        // An invitation sent to the previous email must not work anymore: a new one is sent to the new email when needed
        if ($user->wasChanged('email')) {
            $previousUser = (clone $user)->setAttribute('email', $user->getOriginal('email'));

            Password::broker(ResetPassword::INVITATION_PASSWORD_BROKER)->deleteToken($previousUser);
        }

        // Handle role protection after update
        if ($user->isRootUserProtected()) {
            // If roles were modified, we need to check if we should revert them
            // Since we can't easily detect role changes in the observer,
            // we'll handle this in the model methods themselves
        }
    }

    /**
     * Guard the deletion of a user: the root user can never be deleted, and neither can a user who has assignments.
     *
     * It is not the "deleting" handler of the observer: `User::booting()` registers it before the "deleting" listeners
     * of the traits (e.g. `HasRoles`, which detaches the roles), so that a refused deletion leaves the user untouched.
     *
     * A user who has assignments cannot be deleted: the history of the territories (the dates they were last worked,
     * the coverage, the campaigns...) would be lost with them, and their assignment in progress would disappear. The
     * database refuses it too. Their access can be disabled instead (`can_login`), making them a virtual user.
     *
     * @return false|null false cancels the deletion; null lets it (and the other "deleting" listeners) go on
     */
    public function guardDeletion(User $user): ?bool
    {
        // Prevent deletion of root user under any circumstances
        if ($user->isRootUser()) {
            throw new Exception(__('profile.root_user_protection.account_deletion_not_allowed'));
        }

        if ($user->hasAssignments()) {
            return false;
        }

        return null;
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
