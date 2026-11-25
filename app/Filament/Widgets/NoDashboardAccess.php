<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Shown on the dashboard instead of an empty page to the users who cannot see any of its other widgets (e.g. a user
 * with no role yet).
 */
class NoDashboardAccess extends Widget
{
    /**
     * The widgets of the dashboard that this one stands in for.
     *
     * @var list<class-string<Widget>>
     */
    public const DASHBOARD_WIDGETS = [
        StatsOverview::class,
        TerritoryCoverage::class,
        TerritoryGlobalMap::class,
    ];

    protected string $view = 'filament.widgets.no-dashboard-access';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        foreach (self::DASHBOARD_WIDGETS as $widget) {
            if ($widget::canView()) {
                return false;
            }
        }

        return true;
    }
}
