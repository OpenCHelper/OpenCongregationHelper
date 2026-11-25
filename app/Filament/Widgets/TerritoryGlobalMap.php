<?php

namespace App\Filament\Widgets;

use App\Enums\TerritoryMapCategory;
use App\Enums\TerritoryMapMode;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\Campaign;
use App\Models\Territory;
use App\Settings\TerritorySettings;
use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Polygon;
use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Session;
use Override;

class TerritoryGlobalMap extends MapWidget
{
    protected string $view = 'filament.widgets.territory-global-map';

    /**
     * Only who can view the territories sees them on the map.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Territory::class) ?? false;
    }

    protected int|Closure $maxZoom = 18;

    protected int|Closure $minZoom = 2;

    // Multiple layers
    protected TileLayer|string|array|Closure $tileLayersUrl = [
        'Street Map' => TileLayer::OpenStreetMap,
        'Google Streets' => TileLayer::GoogleStreets,
        'Google Satellite' => TileLayer::GoogleSatellite,
        'Google Hybrid' => TileLayer::GoogleHybrid,
    ];

    /**
     * How the territories are colored, remembered for the whole session.
     */
    #[Session]
    public TerritoryMapMode $mapMode = TerritoryMapMode::ZONE;

    /**
     * The campaign whose progress is shown when the territories are colored by campaign status.
     */
    #[Session]
    public ?int $campaignId = null;

    /**
     * The territories shown on the map, loaded once per request.
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
        $this->normalizeSelection();
    }

    public function updated(): void
    {
        $this->territories = null;

        $this->normalizeSelection();
    }

    #[Override]
    public function getHeading(): string
    {
        return __('filament-widgets.territory_global_map.heading');
    }

    /**
     * Open on the default centre of the Settings pages (or of `.env`), like the other maps with nothing to show yet.
     *
     * @return array{float, float}
     */
    #[Override]
    protected function getMapCenter(): array
    {
        return $this->evaluate($this->mapCenter) ?? $this->territorySettings->map_default_center;
    }

    #[Override]
    protected function getDefaultZoom(): int
    {
        return 12;
    }

    #[Override]
    protected function getMapHeight(): int
    {
        return 600;
    }

    #[Override]
    public function getColumnSpan(): int|string|array
    {
        return 3;
    }

    #[Override]
    public function getFitBounds(): bool
    {
        return true;
    }

    #[Override]
    public function hasFullscreenControl(): bool
    {
        return true;
    }

    /**
     * Clicking the map only opens the popups, in the browser: the widget is not re-rendered, which would zoom the map
     * back out to show all the territories.
     */
    #[Override]
    #[Renderless]
    public function handleMapClick(float $latitude, float $longitude): void {}

    /**
     * @see self::handleMapClick()
     */
    #[Override]
    #[Renderless]
    public function handleLayerClick(string|BaseLayer $layerId): void {}

    /**
     * Get the map modes the user can choose from. Coloring by campaign status requires a campaign that is not terminated.
     *
     * @return array<string, string>
     */
    public function getMapModeOptions(): array
    {
        $hasCampaigns = filled($this->getCampaignOptions());

        return collect(TerritoryMapMode::cases())
            ->reject(fn (TerritoryMapMode $mapMode): bool => $mapMode === TerritoryMapMode::CAMPAIGN && ! $hasCampaigns)
            ->mapWithKeys(fn (TerritoryMapMode $mapMode): array => [$mapMode->value => $mapMode->getLabel()])
            ->all();
    }

    /**
     * Get the campaigns whose progress can be shown: those not terminated, the most recent first.
     *
     * @return array<int, string>
     */
    public function getCampaignOptions(): array
    {
        return Campaign::query()
            ->notTerminated()
            ->orderByDesc('valid_from')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Get the legend of the colors shown on the map, with the number of territories of each color.
     *
     * @return list<array{label: string, color: string, count: int}>
     */
    public function getLegend(): array
    {
        $territories = $this->getTerritories();

        if ($this->mapMode === TerritoryMapMode::ZONE) {
            return $territories
                ->groupBy('territory_zone_id')
                ->map(fn (Collection $zoneTerritories): array => [
                    'label' => $zoneTerritories->first()->territoryZone?->name ?? '',
                    'color' => $this->getCssColor($zoneTerritories->first()->territoryZone?->color),
                    'count' => $zoneTerritories->count(),
                ])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        }

        $territoriesCountByCategory = $territories->countBy(
            fn (Territory $territory): string => $this->getTerritoryCategory($territory)->value,
        );

        return array_map(fn (TerritoryMapCategory $category): array => [
            'label' => $category->getLabel(),
            'color' => $this->getCssColor($category->getColor()),
            'count' => $territoriesCountByCategory->get($category->value, 0),
        ], $this->mapMode->getCategories());
    }

    #[Override]
    protected function getShapes(): array
    {
        $polygons = [];

        foreach ($this->getTerritories() as $territory) {
            $category = $this->getTerritoryCategory($territory);
            $color = $category?->getColor() ?? ($territory->territoryZone?->color ?: null);
            $popupContent = $this->getTerritoryPopupContent($territory, $category);

            foreach ($this->getTerritoryPolygons($territory) as $index => $coordinates) {
                $polygons[] = Polygon::make($coordinates)
                    // The map only redraws the polygons whose id changed, so the id must change with their look
                    ->id(sprintf('territory-%d-%d-%s', $territory->id, $index, md5(json_encode([$color, $popupContent, $coordinates]))))
                    ->color($color)
                    ->fillColor($color)
                    ->title(e($territory->name))
                    ->popupContent($popupContent)
                    // The layers control shows the group names as HTML
                    ->group($territory->territoryZone !== null ? e($territory->territoryZone->name) : null);
            }
        }

        return $polygons;
    }

    /**
     * Get the territories shown on the map, with the data needed to color them in the current map mode.
     *
     * @return Collection<int, Territory>
     */
    protected function getTerritories(): Collection
    {
        if ($this->territories !== null) {
            return $this->territories;
        }

        $isAssignmentInProgress = fn (Builder $query): Builder => $query->whereNull('ended_at');

        return $this->territories = Territory::query()
            ->with('territoryZone')
            ->whereNotNull('geojson')
            ->when($this->getShownTerritoryTypes() === 'residential', fn (Builder $query) => $query->where('is_commercial', false))
            ->when($this->getShownTerritoryTypes() === 'commercial', fn (Builder $query) => $query->where('is_commercial', true))
            ->when(
                in_array($this->mapMode, [TerritoryMapMode::STATUS, TerritoryMapMode::CAMPAIGN], true),
                fn (Builder $query) => $query->withExists(['assignments as has_open_assignment' => $isAssignmentInProgress]),
            )
            ->when($this->mapMode === TerritoryMapMode::FREQUENCY, fn (Builder $query) => $query->withCount([
                'assignments as recent_assignments_count' => fn (Builder $query) => $query->where('started_at', '>=', now()->subMonthsNoOverflow($this->territorySettings->frequency_period_months)),
            ]))
            ->when($this->mapMode === TerritoryMapMode::CAMPAIGN, fn (Builder $query) => $query->withExists([
                'assignments as is_worked_in_campaign' => fn (Builder $query) => $query
                    ->where('campaign_id', $this->campaignId)
                    ->whereNotNull('ended_at'),
                'assignments as is_in_progress_in_campaign' => fn (Builder $query) => $isAssignmentInProgress($query)
                    ->where('campaign_id', $this->campaignId),
            ]))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the category that sets the color of a territory, or `null` when territories are colored by zone.
     */
    protected function getTerritoryCategory(Territory $territory): ?TerritoryMapCategory
    {
        return match ($this->mapMode) {
            TerritoryMapMode::ZONE => null,
            TerritoryMapMode::STATUS => TerritoryMapCategory::fromOpenAssignment((bool) $territory->has_open_assignment),
            TerritoryMapMode::FREQUENCY => TerritoryMapCategory::fromRecentAssignmentsCount((int) $territory->recent_assignments_count),
            TerritoryMapMode::CAMPAIGN => TerritoryMapCategory::fromCampaignProgress(
                isWorkedInCampaign: (bool) $territory->is_worked_in_campaign,
                isInProgressInCampaign: (bool) $territory->is_in_progress_in_campaign,
                hasOpenAssignment: (bool) $territory->has_open_assignment,
            ),
        };
    }

    /**
     * Get the HTML shown when a territory is clicked: its zone, its category and a link to its page.
     */
    protected function getTerritoryPopupContent(Territory $territory, ?TerritoryMapCategory $category): string
    {
        $lines = [];

        if ($territory->territoryZone !== null) {
            $lines[] = e(__('filament-widgets.territory_global_map.popup_zone', ['zone' => $territory->territoryZone->name]));
        }

        if ($category !== null) {
            $lines[] = e($category->getLabel());
        }

        if ($this->mapMode === TerritoryMapMode::FREQUENCY) {
            $lines[] = e(__('filament-widgets.territory_global_map.popup_recent_assignments', [
                'period' => TerritoryMapCategory::getFrequencyPeriod(),
                'count' => (int) $territory->recent_assignments_count,
            ]));
        }

        if (TerritoryResource::canView($territory)) {
            $lines[] = sprintf(
                '<a href="%s" style="text-decoration: underline">%s</a>',
                e(TerritoryResource::getUrl('view', ['record' => $territory])),
                e(__('filament-widgets.territory_global_map.go_to_territory')),
            );
        }

        return implode('<br>', $lines);
    }

    /**
     * Get the polygons of the map of a territory, as lists of rings of [latitude, longitude] points.
     *
     * @return list<list<list<array{0: float, 1: float}>>>
     */
    protected function getTerritoryPolygons(Territory $territory): array
    {
        $geojson = json_decode($territory->geojson, true);

        $polygons = [];

        foreach ($geojson['features'] ?? [] as $feature) {
            if (($feature['geometry']['type'] ?? null) !== 'Polygon') {
                continue;
            }

            // GeoJSON points are [longitude, latitude], while Leaflet expects [latitude, longitude]
            $polygons[] = array_map(
                fn (array $ring): array => array_map(fn (array $point): array => [$point[1], $point[0]], $ring),
                $feature['geometry']['coordinates'],
            );
        }

        return $polygons;
    }

    /**
     * Get the CSS color of a zone color or of a category palette, as filled in the polygons.
     *
     * @param  string|array<int, string>|null  $color
     */
    protected function getCssColor(string|array|null $color): string
    {
        if (blank($color)) {
            $color = Color::Blue;
        }

        if (is_array($color)) {
            $color = $color[500];
        }

        return Color::convertToRgb($color);
    }

    /**
     * Make sure that the selected campaign can be shown, falling back to the default one, and stop coloring by
     * campaign status when there is no campaign to show.
     */
    protected function normalizeSelection(): void
    {
        if ($this->campaignId === null || ! array_key_exists($this->campaignId, $this->getCampaignOptions())) {
            $this->campaignId = $this->getDefaultCampaignId();
        }

        if ($this->mapMode === TerritoryMapMode::CAMPAIGN && $this->campaignId === null) {
            $this->mapMode = TerritoryMapMode::ZONE;
        }
    }

    /**
     * Get the campaign shown by default: the active one that started last or, if none is active, the next one.
     */
    protected function getDefaultCampaignId(): ?int
    {
        return Campaign::query()->withActive()->orderByDesc('valid_from')->value('id')
            ?? Campaign::query()->notTerminated()->orderBy('valid_from')->value('id');
    }

    protected function getShownTerritoryTypes(): string
    {
        return 'residential';
    }
}
