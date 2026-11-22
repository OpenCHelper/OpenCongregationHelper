<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Settings\TerritorySettings;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Livewire\Attributes\Session;

/**
 * How much of the territory was worked in a period of time (at least one assignment overlapping it: started before its
 * end, and in progress or returned in it), and the territories never worked or not worked for the longest time.
 */
class TerritoryCoverage extends Widget
{
    /**
     * How many of the territories not worked for the longest time are listed.
     */
    public const LEAST_RECENTLY_WORKED_COUNT = 5;

    protected string $view = 'filament.widgets.territory-coverage';

    protected int|string|array $columnSpan = 'full';

    /**
     * The period, in months, remembered for the whole session (the default one of the settings until chosen).
     */
    #[Session]
    public ?int $months = null;

    /**
     * The territories with the end of their last returned assignment and whether they are in progress, loaded once
     * per request.
     *
     * @var Collection<int, Territory>|null
     */
    protected ?Collection $territories = null;

    /**
     * The territory settings, injected on every request.
     */
    protected TerritorySettings $territorySettings;

    public function boot(TerritorySettings $territorySettings): void
    {
        $this->territorySettings = $territorySettings;
    }

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
        return collect($this->getPeriods())
            ->mapWithKeys(fn (int $months): array => [$months => trans_choice('filament-widgets.territory_coverage.period', $months, ['count' => $months])])
            ->all();
    }

    /**
     * The periods, in months, the user can choose from (from the settings).
     *
     * @return list<int>
     */
    public function getPeriods(): array
    {
        $periods = collect($this->territorySettings->coverage_periods_months)
            ->map(fn (mixed $months): int => (int) $months)
            ->filter(fn (int $months): bool => $months > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $periods !== [] ? $periods : TerritorySettings::defaultValue('coverage_periods_months');
    }

    /**
     * The period, in months, shown until the user chooses another one (from the settings).
     */
    public function getDefaultPeriod(): int
    {
        $periods = $this->getPeriods();
        $default = (int) $this->territorySettings->coverage_default_period_months;

        return in_array($default, $periods, true) ? $default : $periods[0];
    }

    /**
     * The coverage of the period, counting all the assignments.
     *
     * @return array{worked: int, total: int, percentage: float, label: string}
     */
    public function getCoverage(): array
    {
        return $this->coverageOf('last_worked_at', 'is_in_progress');
    }

    /**
     * The coverage of the period, counting only the assignments outside of campaigns.
     *
     * @return array{worked: int, total: int, percentage: float, label: string}
     */
    public function getCoverageExcludingCampaigns(): array
    {
        return $this->coverageOf('last_worked_outside_campaigns_at', 'is_in_progress_outside_campaigns');
    }

    /**
     * The territories never worked first, then those whose last returned assignment ended the longest ago, and last
     * those in progress (being worked now).
     *
     * @return Collection<int, Territory>
     */
    public function getLeastRecentlyWorkedTerritories(): Collection
    {
        return $this->getTerritories()
            ->sortBy([
                fn (Territory $a, Territory $b): int => (bool) $a->is_in_progress <=> (bool) $b->is_in_progress,
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
        return now()->subMonthsNoOverflow($this->months ?? $this->getDefaultPeriod());
    }

    /**
     * @return array{worked: int, total: int, percentage: float, label: string}
     */
    protected function coverageOf(string $lastWorkedAttribute, string $inProgressAttribute): array
    {
        $territories = $this->getTerritories();
        $periodStart = $this->getPeriodStart();

        $total = $territories->count();
        $worked = $territories
            ->filter(fn (Territory $territory): bool => (bool) $territory->{$inProgressAttribute}
                || ($territory->{$lastWorkedAttribute} !== null && Carbon::parse($territory->{$lastWorkedAttribute})->greaterThanOrEqualTo($periodStart)))
            ->count();

        // Rounded down, so that 100% is only shown when every territory was worked
        $percentage = $total > 0 ? floor($worked * 1000 / $total) / 10 : 0.0;

        return [
            'worked' => $worked,
            'total' => $total,
            'percentage' => $percentage,
            'label' => __('filament-widgets.territory_coverage.value', [
                'percentage' => Number::percentage($percentage, maxPrecision: 1, locale: app()->getLocale()),
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
            ->withExists([
                'assignments as is_in_progress' => fn (Builder $query) => $this->inProgress($query),
                'assignments as is_in_progress_outside_campaigns' => fn (Builder $query) => $this->inProgress($query)->whereNull('campaign_id'),
            ])
            ->get();
    }

    /**
     * Restrict the query to the assignments in progress that have already started.
     *
     * @param  Builder<TerritoryAssignment>  $query
     * @return Builder<TerritoryAssignment>
     */
    protected function inProgress(Builder $query): Builder
    {
        return $query->whereNull('ended_at')->where('started_at', '<=', now());
    }

    protected function normalizePeriod(): void
    {
        if (! in_array($this->months, $this->getPeriods(), true)) {
            $this->months = $this->getDefaultPeriod();
        }
    }
}
