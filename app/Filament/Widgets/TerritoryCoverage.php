<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Livewire\Attributes\Session;

/**
 * How much of the territory was worked (at least one returned assignment) in a period of time,
 * and the territories never worked or not worked for the longest time.
 */
class TerritoryCoverage extends Widget
{
    /**
     * The periods, in months, the user can choose from.
     *
     * @var list<int>
     */
    public const PERIODS = [6, 12, 24];

    public const DEFAULT_PERIOD = 12;

    /**
     * How many of the territories not worked for the longest time are listed.
     */
    public const LEAST_RECENTLY_WORKED_COUNT = 5;

    protected string $view = 'filament.widgets.territory-coverage';

    protected int|string|array $columnSpan = 'full';

    /**
     * The period, in months, remembered for the whole session.
     */
    #[Session]
    public int $months = self::DEFAULT_PERIOD;

    /**
     * The territories with the end of their last returned assignment, loaded once per request.
     *
     * @var Collection<int, Territory>|null
     */
    protected ?Collection $territories = null;

    public function mount(): void
    {
        $this->normalizePeriod();
    }

    public function updatedMonths(): void
    {
        $this->normalizePeriod();
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodOptions(): array
    {
        return collect(self::PERIODS)
            ->mapWithKeys(fn (int $months): array => [$months => trans_choice('filament-widgets.territory_coverage.period', $months, ['count' => $months])])
            ->all();
    }

    /**
     * The coverage of the period, counting all the assignments.
     *
     * @return array{worked: int, total: int, percentage: float, label: string}
     */
    public function getCoverage(): array
    {
        return $this->coverageOf('last_worked_at');
    }

    /**
     * The coverage of the period, counting only the assignments outside of campaigns.
     *
     * @return array{worked: int, total: int, percentage: float, label: string}
     */
    public function getCoverageExcludingCampaigns(): array
    {
        return $this->coverageOf('last_worked_outside_campaigns_at');
    }

    /**
     * The territories never worked first, then those whose last returned assignment ended the longest ago.
     *
     * @return Collection<int, Territory>
     */
    public function getLeastRecentlyWorkedTerritories(): Collection
    {
        return $this->getTerritories()
            ->sortBy([
                fn (Territory $a, Territory $b): int => ($a->last_worked_at !== null) <=> ($b->last_worked_at !== null),
                fn (Territory $a, Territory $b): int => (string) $a->last_worked_at <=> (string) $b->last_worked_at,
                fn (Territory $a, Territory $b): int => strnatcasecmp((string) $a->name, (string) $b->name),
            ])
            ->take(self::LEAST_RECENTLY_WORKED_COUNT)
            ->values();
    }

    /**
     * Get the date the territory was last worked, if any.
     */
    public function getLastWorkedAt(Territory $territory): ?Carbon
    {
        return $territory->last_worked_at !== null ? Carbon::parse($territory->last_worked_at) : null;
    }

    public function getTerritoryUrl(Territory $territory): ?string
    {
        return TerritoryResource::canView($territory) ? TerritoryResource::getUrl('view', ['record' => $territory]) : null;
    }

    /**
     * The start of the chosen period.
     */
    protected function getPeriodStart(): Carbon
    {
        return now()->subMonths($this->months);
    }

    /**
     * @return array{worked: int, total: int, percentage: float, label: string}
     */
    protected function coverageOf(string $lastWorkedAttribute): array
    {
        $territories = $this->getTerritories();
        $periodStart = $this->getPeriodStart();

        $total = $territories->count();
        $worked = $territories
            ->filter(fn (Territory $territory): bool => $territory->{$lastWorkedAttribute} !== null
                && Carbon::parse($territory->{$lastWorkedAttribute})->greaterThanOrEqualTo($periodStart))
            ->count();

        // Rounded down, so that 100% is only shown when every territory was worked
        $percentage = $total > 0 ? floor($worked * 1000 / $total) / 10 : 0.0;

        return [
            'worked' => $worked,
            'total' => $total,
            'percentage' => $percentage,
            'label' => __('filament-widgets.territory_coverage.value', [
                'percentage' => Number::percentage($percentage, maxPrecision: 1),
                'worked' => $worked,
                'total' => $total,
            ]),
        ];
    }

    /**
     * @return Collection<int, Territory>
     */
    protected function getTerritories(): Collection
    {
        return $this->territories ??= Territory::query()
            ->select(['id', 'name', 'territory_zone_id'])
            ->with('territoryZone:id,name')
            ->withMax(['assignments as last_worked_at' => fn (Builder $query) => $query->returned()], 'ended_at')
            ->withMax(['assignments as last_worked_outside_campaigns_at' => fn (Builder $query) => $query->returned()->whereNull('campaign_id')], 'ended_at')
            ->get();
    }

    protected function normalizePeriod(): void
    {
        if (! in_array($this->months, self::PERIODS, true)) {
            $this->months = self::DEFAULT_PERIOD;
        }
    }
}
