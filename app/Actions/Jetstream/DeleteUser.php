<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
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
        /*
            Commenting this line because it causes the following error when trying to delete the user:
                Call to undefined method Illuminate\Database\Eloquent\Relations\HasMany::detach()

            This happens because Jetstream's `HasTeams` trait uses a `BelongsToMany` relationship for teams, while
            the `User` model's `teams()` method is defined as a `HasMany` relationship.
        */
        // $user->teams()->detach();

        // Delete all teams owned by the user
        $user->ownedTeams->each(function (Team $team) {
            $this->deletesTeams->delete($team);
        });

        // Note: This application uses teams primarily for ownership, not membership
        // If team memberships were needed, you would handle them here with a BelongsToMany relationship
    }
}
