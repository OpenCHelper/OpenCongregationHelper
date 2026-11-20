<?php

namespace App\Enums;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Posters\PosterResource;
use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\Publications\PublicationResource;
use App\Filament\Resources\PublicationSubscribers\PublicationSubscriberResource;
use App\Filament\Resources\StorageLocations\StorageLocationResource;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use App\Filament\Widgets\TerritoryGlobalMap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The departments of the congregation, each one with its own section of the menu.
 *
 * The members of a department can fully manage its sections, without needing a role with their permissions.
 */
enum DepartmentKey: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    // Change the order of the cases to update the order on the UI
    case Territories = 'territories';
    case PublicWitnessing = 'public_witnessing';
    case Publications = 'publications';

    public function getLabel(): string
    {
        return __("departments.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("departments.{$this->value}.description");
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Territories => Heroicon::OutlinedMap,
            self::PublicWitnessing => Heroicon::OutlinedPresentationChartBar,
            self::Publications => Heroicon::OutlinedBookOpen,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Territories => 'success',
            self::PublicWitnessing => 'info',
            self::Publications => 'warning',
        };
    }

    /**
     * The group of the menu that holds the sections of the department.
     */
    public function getNavigationGroup(): NavigationGroup
    {
        return match ($this) {
            self::Territories => NavigationGroup::Territories,
            self::PublicWitnessing => NavigationGroup::PublicWitnessing,
            self::Publications => NavigationGroup::Publications,
        };
    }

    /**
     * The resources managed by the department.
     *
     * @return list<class-string<resource>>
     */
    public function getResources(): array
    {
        return match ($this) {
            self::Territories => [
                TerritoryResource::class,
                TerritoryZoneResource::class,
                TerritoryAssignmentResource::class,
                CampaignResource::class,
            ],
            self::PublicWitnessing => [
                PosterResource::class,
                StorageLocationResource::class,
            ],
            self::Publications => [
                PublicationSubscriberResource::class,
                PublicationRequestResource::class,
                PublicationResource::class,
            ],
        };
    }

    /**
     * The subjects of the permissions (`Action:Subject`) granted to the members of the department: the models of
     * its resources, plus its pages and widgets.
     *
     * @return list<string>
     */
    public function getPermissionSubjects(): array
    {
        $models = array_map(
            static fn (string $resource): string => class_basename($resource::getModel()),
            $this->getResources(),
        );

        return [
            ...$models,
            ...match ($this) {
                self::Territories => [class_basename(TerritoryGlobalMap::class)],
                default => [],
            },
        ];
    }
}
