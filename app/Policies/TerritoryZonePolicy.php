<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TerritoryZone;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TerritoryZonePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TerritoryZone');
    }

    public function view(AuthUser $authUser, TerritoryZone $territoryZone): bool
    {
        return $authUser->can('View:TerritoryZone');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TerritoryZone');
    }

    public function update(AuthUser $authUser, TerritoryZone $territoryZone): bool
    {
        return $authUser->can('Update:TerritoryZone');
    }

    public function delete(AuthUser $authUser, TerritoryZone $territoryZone): bool
    {
        return $authUser->can('Delete:TerritoryZone');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TerritoryZone');
    }

    public function restore(AuthUser $authUser, TerritoryZone $territoryZone): bool
    {
        return $authUser->can('Restore:TerritoryZone');
    }

    public function forceDelete(AuthUser $authUser, TerritoryZone $territoryZone): bool
    {
        return $authUser->can('ForceDelete:TerritoryZone');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TerritoryZone');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TerritoryZone');
    }

    public function replicate(AuthUser $authUser, TerritoryZone $territoryZone): bool
    {
        return $authUser->can('Replicate:TerritoryZone');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TerritoryZone');
    }
}
