<?php

namespace App\Filament\Resources\PublicationRequests\Widgets;

use App\Enums\PublicationRequestStatus;
use App\Models\PublicationRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The requests still to order or to retrieve, the ones waiting for their delivery, and the copies delivered this month.
 */
class PublicationRequestsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $copies = fn ($query): int => (int) $query->sum('quantity');

        return [
            Stat::make(__('filament-resources.publication_request.tab_to_order'), PublicationRequest::query()->toOrder()->count())
                ->description(trans_choice('filament-resources.publication_request.widget_copies', $total = $copies(PublicationRequest::query()->toOrder()), ['count' => $total]))
                ->descriptionIcon(PublicationRequestStatus::Requested->getIcon())
                ->color(PublicationRequestStatus::Requested->getColor()),
            Stat::make(__('filament-resources.publication_request.tab_ordered'), PublicationRequest::query()->ordered()->count())
                ->description(trans_choice('filament-resources.publication_request.widget_copies', $total = $copies(PublicationRequest::query()->ordered()), ['count' => $total]))
                ->descriptionIcon(PublicationRequestStatus::Ordered->getIcon())
                ->color(PublicationRequestStatus::Ordered->getColor()),
            Stat::make(
                __('filament-resources.publication_request.widget_delivered_this_month'),
                $copies(PublicationRequest::query()->whereBetween('delivered_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])),
            )
                ->description(__('filament-resources.publication_request.widget_delivered_this_month_description'))
                ->descriptionIcon(PublicationRequestStatus::Delivered->getIcon())
                ->color(PublicationRequestStatus::Delivered->getColor()),
        ];
    }
}
