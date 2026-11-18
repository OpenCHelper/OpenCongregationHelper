<?php

namespace App\Filament\Resources\ServiceGroups\Pages;

use App\Filament\Resources\ServiceGroups\ServiceGroupResource;
use App\Models\ServiceGroup;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

/**
 * Board with a column for each service group, to move the publishers between groups by dragging them.
 */
class OrganizeServiceGroups extends Page
{
    protected static string $resource = ServiceGroupResource::class;

    protected string $view = 'filament.resources.service-groups.pages.organize-service-groups';

    /**
     * Moving a publisher changes their user, so the page needs the permission to edit users too.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('viewAny', ServiceGroup::class) && Gate::allows('update', User::class);
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-pages.organize_service_groups');
    }

    /**
     * @return Collection<int, ServiceGroup>
     */
    #[Computed]
    public function serviceGroups(): Collection
    {
        return ServiceGroup::query()
            ->orderBy('name')
            ->with(['members' => fn ($query) => $query->orderBy('first_name')->orderBy('last_name')])
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function publishersWithoutGroup(): Collection
    {
        return User::query()
            ->whereNull('service_group_id')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Move a publisher to another group, or out of any group when `$serviceGroupId` is `null`.
     */
    public function movePublisher(int $userId, ?int $serviceGroupId): void
    {
        $user = User::findOrFail($userId);
        $serviceGroup = $serviceGroupId !== null ? ServiceGroup::findOrFail($serviceGroupId) : null;

        // Both the group the publisher leaves and the one they join change
        Gate::authorize('update', $user);
        foreach (array_filter([$user->serviceGroup, $serviceGroup]) as $changedGroup) {
            Gate::authorize('update', $changedGroup);
        }

        $user->serviceGroup()->associate($serviceGroup)->save();

        Notification::make()
            ->success()
            ->title($serviceGroup !== null
                ? __('filament-resources.service_group.publisher_moved', ['name' => $user->getFullName(), 'group' => $serviceGroup->name])
                : __('filament-resources.service_group.publisher_removed', ['name' => $user->getFullName()]))
            ->send();
    }
}
