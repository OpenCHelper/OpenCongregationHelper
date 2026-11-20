<?php

namespace App\Filament\Resources\PublicationSubscribers\Widgets;

use App\Filament\Resources\PublicationSubscribers\Pages\ManagePublicationSubscribers;
use App\Models\Publication;
use App\Models\PublicationSubscriber;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The copies of each publication to order, among the publishers shown by the table (all, or the filtered ones).
 */
class PublicationTotals extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ManagePublicationSubscribers::class;
    }

    protected function getStats(): array
    {
        $totals = PublicationSubscriber::totals($this->getPageTableQuery()->pluck('publication_subscribers.id'));

        // Only the publications received by someone
        return Publication::offeredRenewable()
            ->filter(fn (Publication $publication): bool => isset($totals[$publication->getKey()]))
            ->map(function (Publication $publication) use ($totals): Stat {
                ['copies' => $copies, 'publishers' => $publishers] = $totals[$publication->getKey()] ?? ['copies' => 0, 'publishers' => 0];

                return Stat::make($publication->name, $copies)
                    ->description(trans_choice('filament-resources.publication_subscriber.widget_publishers', $publishers, ['count' => $publishers]))
                    ->descriptionIcon('heroicon-o-users')
                    ->color('info');
            })
            ->values()
            ->all();
    }
}
