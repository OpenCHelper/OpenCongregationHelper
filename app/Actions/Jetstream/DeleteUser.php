<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(protected DeletesTeams $deletesTeams) {}

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

        DB::transaction(function () use ($user) {
            $this->deleteTeams($user);
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(User $user): void
    {
        $user->teams()->detach();

        // Delete all teams owned by the user
        $user->ownedTeams->each(function (Team $team) {
            $this->deletesTeams->delete($team);
        });
    }
}
