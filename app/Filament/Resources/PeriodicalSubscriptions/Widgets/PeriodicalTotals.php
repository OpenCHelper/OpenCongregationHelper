<?php

namespace App\Filament\Resources\PeriodicalSubscriptions\Widgets;

use App\Enums\Periodical;
use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Models\PeriodicalSubscription;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The copies of each periodical to order, among the publishers shown by the table (all, or the filtered ones).
 */
class PeriodicalTotals extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ManagePeriodicalSubscriptions::class;
    }

    protected function getStats(): array
    {
        $totals = PeriodicalSubscription::totals($this->getPageTableQuery()->get());

        return array_map(
            fn (Periodical $periodical): Stat => Stat::make($periodical->getLabel(), $totals[$periodical->value]['copies'])
                ->description(trans_choice('filament-resources.periodical_subscription.widget_publishers', $totals[$periodical->value]['publishers'], ['count' => $totals[$periodical->value]['publishers']]))
                ->descriptionIcon('heroicon-o-users')
                ->color($periodical->getColor()),
            Periodical::cases(),
        );
    }
}
