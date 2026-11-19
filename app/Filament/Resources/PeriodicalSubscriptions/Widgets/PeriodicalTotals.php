<?php

namespace App\Filament\Resources\PeriodicalSubscriptions\Widgets;

use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Models\PeriodicalSubscription;
use App\Models\Publication;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The copies of each publication to order, among the publishers shown by the table (all, or the filtered ones).
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
        $totals = PeriodicalSubscription::totals($this->getPageTableQuery()->pluck('periodical_subscriptions.id'));

        // Only the publications received by someone, which can be many (e.g. books)
        return Publication::offered()
            ->filter(fn (Publication $publication): bool => isset($totals[$publication->getKey()]))
            ->map(function (Publication $publication) use ($totals): Stat {
                ['copies' => $copies, 'publishers' => $publishers] = $totals[$publication->getKey()] ?? ['copies' => 0, 'publishers' => 0];

                return Stat::make($publication->name, $copies)
                    ->description(trans_choice('filament-resources.periodical_subscription.widget_publishers', $publishers, ['count' => $publishers]))
                    ->descriptionIcon('heroicon-o-users')
                    ->color('info');
            })
            ->values()
            ->all();
    }
}
