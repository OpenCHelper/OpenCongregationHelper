<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Campaign;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Only the actions offered by the resource have a permission (see `resources.manage` in `config/filament-shield.php`,
 * which `ShieldSeeder` creates): the others are denied, since Filament would allow an action without a method here.
 */
class CampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Campaign');
    }

    public function view(AuthUser $authUser, Campaign $campaign): bool
    {
        return $authUser->can('View:Campaign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Campaign');
    }

    public function update(AuthUser $authUser, Campaign $campaign): bool
    {
        return $authUser->can('Update:Campaign');
    }

    public function delete(AuthUser $authUser, Campaign $campaign): bool
    {
        return $authUser->can('Delete:Campaign');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Campaign');
    }

    public function restore(AuthUser $authUser, Campaign $campaign): bool
    {
        return $authUser->can('Restore:Campaign');
    }

    public function forceDelete(AuthUser $authUser, Campaign $campaign): bool
    {
        return $authUser->can('ForceDelete:Campaign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Campaign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Campaign');
    }

    public function replicate(AuthUser $authUser, Campaign $campaign): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
