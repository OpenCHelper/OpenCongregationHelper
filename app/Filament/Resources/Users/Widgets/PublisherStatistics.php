<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Statistics about the territories a publisher takes: the zones and the territories they are assigned most often.
 */
class PublisherStatistics extends Widget
{
    /**
     * How many territories are listed among the preferred ones.
     */
    public const PREFERRED_TERRITORIES_LIMIT = 5;

    protected string $view = 'filament.widgets.publisher-statistics';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    /**
     * The total number of assignments, computed once per request.
     */
    protected ?int $assignmentsCount = null;

    /**
     * Whether the current user can see the statistics of the given publisher: those who cannot see all the assignments
     * only see their own, since the statistics of the others would always look empty.
     */
    public static function canViewStatisticsOf(Model $publisher): bool
    {
        return TerritoryAssignmentResource::canViewAllAssignments() || $publisher->is(Auth::user());
    }

    /**
     * The total number of assignments of the publisher (in progress or returned).
     */
    public function getAssignmentsCount(): int
    {
        return $this->assignmentsCount ??= $this->getAssignmentsQuery()->count();
    }

    /**
     * The zones of the territories assigned to the publisher, the most frequent first.
     *
     * @return Collection<int, array{name: string, color: ?string, count: int, percentage: float}>
     */
    public function getPreferredZones(): Collection
    {
        $zones = $this->getAssignmentsQuery()
            ->join('territories', 'territories.id', '=', 'territory_assignments.territory_id')
            ->join('territory_zones', 'territory_zones.id', '=', 'territories.territory_zone_id')
            ->groupBy('territory_zones.id', 'territory_zones.name', 'territory_zones.color')
            ->select(['territory_zones.name', 'territory_zones.color'])
            ->selectRaw('count(*) as assignments_count')
            ->orderByDesc('assignments_count')
            ->orderBy('territory_zones.name')
            ->get();

        return $this->withPercentages($zones->map(fn (object $zone): array => [
            'name' => $zone->name,
            'color' => $zone->color,
            'count' => (int) $zone->assignments_count,
        ]));
    }

    /**
     * The territories assigned most often to the publisher, the most frequent first.
     *
     * @return Collection<int, array{name: string, zone: ?string, color: ?string, url: ?string, count: int, percentage: float}>
     */
    public function getPreferredTerritories(): Collection
    {
        $territories = $this->getAssignmentsQuery()
            ->join('territories', 'territories.id', '=', 'territory_assignments.territory_id')
            ->leftJoin('territory_zones', 'territory_zones.id', '=', 'territories.territory_zone_id')
            ->groupBy('territories.id', 'territories.name', 'territories.deleted_at', 'territory_zones.name', 'territory_zones.color')
            ->select(['territories.id', 'territories.name', 'territories.deleted_at', 'territory_zones.name as zone_name', 'territory_zones.color as zone_color'])
            ->selectRaw('count(*) as assignments_count')
            ->orderByDesc('assignments_count')
            ->orderBy('territories.name')
            ->limit(self::PREFERRED_TERRITORIES_LIMIT)
            ->get();

        $canViewTerritories = TerritoryResource::canViewAny();

        return $this->withPercentages($territories->map(fn (object $territory): array => [
            'name' => $territory->name,
            'zone' => $territory->zone_name,
            'color' => $territory->zone_color,
            'url' => $canViewTerritories && $territory->deleted_at === null
                ? TerritoryResource::getUrl('view', ['record' => $territory->id])
                : null,
            'count' => (int) $territory->assignments_count,
        ]));
    }

    /**
     * The assignments of the publisher that the current user is allowed to see.
     */
    protected function getAssignmentsQuery(): QueryBuilder
    {
        /** @var Builder<TerritoryAssignment> $query */
        $query = TerritoryAssignmentResource::scopeToVisibleAssignments(
            TerritoryAssignment::query()->where('territory_assignments.assignee_id', $this->record?->getKey())
        );

        return $query->toBase();
    }

    /**
     * Add to each item its share of the publisher's assignments.
     *
     * @template TItem of array{count: int}
     *
     * @param  Collection<int, TItem>  $items
     * @return Collection<int, TItem&array{percentage: float}>
     */
    protected function withPercentages(Collection $items): Collection
    {
        $total = $this->getAssignmentsCount();

        return $items->map(fn (array $item): array => [
            ...$item,
            'percentage' => $total > 0 ? round($item['count'] * 100 / $total, 1) : 0.0,
        ]);
    }
}
