<?php

namespace App\Policies;

use App\Models\SharedUrl;
use App\Models\User;

class SharedUrlPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return SharedUrl::isSharingEnabled();
    }

    /**
     * Determine whether the user can view the model: not while sharing is disabled, so that its link stops working.
     */
    public function view(?User $user, SharedUrl $sharedUrl): bool
    {
        return SharedUrl::isSharingEnabled();
    }

    /**
     * Determine whether the user can create models: not while sharing is disabled.
     */
    public function create(User $user): bool
    {
        return SharedUrl::isSharingEnabled();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SharedUrl $sharedUrl): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SharedUrl $sharedUrl): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SharedUrl $sharedUrl): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SharedUrl $sharedUrl): bool
    {
        return true;
    }
}
