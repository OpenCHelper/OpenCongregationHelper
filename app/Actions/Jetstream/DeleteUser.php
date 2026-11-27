<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        // Prevent deletion of root user under any circumstances
        if ($user->isRootUser()) {
            throw new Exception(__('profile.root_user_protection.account_deletion_not_allowed'));
        }

        // The history of the territories would be lost with the user (see `UserObserver::guardDeletion()`): the error is
        // shown in the confirmation modal, before the user is logged out
        if ($user->hasAssignments()) {
            throw ValidationException::withMessages([
                'password' => [__('profile.delete_account.has_assignments')],
            ]);
        }

        // Nobody could manage the application anymore (see `UserObserver::guardDeletion()`)
        if ($user->isLastSuperAdmin()) {
            throw ValidationException::withMessages([
                'password' => [__('profile.delete_account.last_super_admin')],
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }
}
