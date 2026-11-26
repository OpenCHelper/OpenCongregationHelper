<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ServiceGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ServiceGroup');
    }

    public function view(AuthUser $authUser, ServiceGroup $serviceGroup): bool
    {
        return $authUser->can('View:ServiceGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ServiceGroup');
    }

    public function update(AuthUser $authUser, ServiceGroup $serviceGroup): bool
    {
        return $authUser->can('Update:ServiceGroup');
    }

    public function delete(AuthUser $authUser, ServiceGroup $serviceGroup): bool
    {
        return $authUser->can('Delete:ServiceGroup');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ServiceGroup');
    }

    public function replicate(AuthUser $authUser, ServiceGroup $serviceGroup): bool
    {
        return $authUser->can('Replicate:ServiceGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ServiceGroup');
    }
}
