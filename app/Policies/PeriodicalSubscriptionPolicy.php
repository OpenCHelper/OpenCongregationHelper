<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PeriodicalSubscription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PeriodicalSubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PeriodicalSubscription');
    }

    public function view(AuthUser $authUser, PeriodicalSubscription $periodicalSubscription): bool
    {
        return $authUser->can('View:PeriodicalSubscription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PeriodicalSubscription');
    }

    public function update(AuthUser $authUser, PeriodicalSubscription $periodicalSubscription): bool
    {
        return $authUser->can('Update:PeriodicalSubscription');
    }

    public function delete(AuthUser $authUser, PeriodicalSubscription $periodicalSubscription): bool
    {
        return $authUser->can('Delete:PeriodicalSubscription');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PeriodicalSubscription');
    }

    public function restore(AuthUser $authUser, PeriodicalSubscription $periodicalSubscription): bool
    {
        return $authUser->can('Restore:PeriodicalSubscription');
    }

    public function forceDelete(AuthUser $authUser, PeriodicalSubscription $periodicalSubscription): bool
    {
        return $authUser->can('ForceDelete:PeriodicalSubscription');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PeriodicalSubscription');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PeriodicalSubscription');
    }

    public function replicate(AuthUser $authUser, PeriodicalSubscription $periodicalSubscription): bool
    {
        return $authUser->can('Replicate:PeriodicalSubscription');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PeriodicalSubscription');
    }
}
