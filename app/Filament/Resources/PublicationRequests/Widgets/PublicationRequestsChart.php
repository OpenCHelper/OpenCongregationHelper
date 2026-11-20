<?php

namespace App\Filament\Resources\PublicationRequests\Widgets;

use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Models\PublicationRequest;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The timeline of the requests: the copies requested and delivered in each of the last 12 months, for all the
 * publications or one of them, to see at a glance when there were more requests.
 */
class PublicationRequestsChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = 'all';

    public function getHeading(): string|Htmlable|null
    {
        return __('filament-resources.publication_request.chart_heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('filament-resources.publication_request.chart_description');
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        $options = ['all' => __('filament-resources.publication_request.chart_all_publications')];

        foreach (PublicationRequestResource::getPublicationOptions() as $publications) {
            $options += $publications;
        }

        return $options;
    }

    /**
     * Get the copies of each of the last 12 months, by the given date of the requests.
     *
     * @return array<string, int> keyed by month (`Y-m`)
     */
    public function getMonthlyCopies(string $dateColumn): array
    {
        $end = CarbonImmutable::today()->endOfMonth();

        return Trend::query($this->getRequestsQuery())
            ->dateColumn($dateColumn)
            ->between(start: $end->subMonthsNoOverflow(11)->startOfMonth(), end: $end)
            ->perMonth()
            ->sum('quantity')
            ->mapWithKeys(fn (TrendValue $value): array => [$value->date => (int) $value->aggregate])
            ->all();
    }

    /**
     * @return Builder<PublicationRequest>
     */
    protected function getRequestsQuery(): Builder
    {
        return PublicationRequest::query()
            ->when(filled($this->filter) && $this->filter !== 'all', fn (Builder $query): Builder => $query->where('publication_id', $this->filter));
    }

    protected function getData(): array
    {
        $requested = $this->getMonthlyCopies('requested_at');
        $delivered = $this->getMonthlyCopies('delivered_at');

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => __('filament-resources.publication_request.chart_requested'),
                    'data' => array_values($requested),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'borderColor' => 'rgb(245, 158, 11)',
                ],
                [
                    'type' => 'line',
                    'label' => __('filament-resources.publication_request.chart_delivered'),
                    'data' => array_values($delivered),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(
                fn (string $month): string => CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth()->translatedFormat('M Y'),
                array_keys($requested),
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
