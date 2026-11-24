<?php

namespace App\Filament\Resources\Campaigns\Widgets;

use App\Models\Campaign;
use App\Models\Territory;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;

class CampaignAssignmentsChart extends ChartWidget
{
    public ?Campaign $record = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('filament-resources.campaign.widget_assignments_chart_heading');
    }

    /**
     * Counts each territory once, in a single slice, so that the slices add up to the number of territories: a
     * territory assigned more than once in the campaign is in progress if one of its assignments is, completed
     * otherwise.
     */
    protected function getData(): array
    {
        $inCampaign = fn (Builder $query): Builder => $query->where('campaign_id', $this->record->id);
        $inProgressInCampaign = fn (Builder $query): Builder => $inCampaign($query)->whereNull('ended_at');

        return [
            'datasets' => [
                [
                    'label' => __('filament-resources.campaign.widget_assignments_chart_dataset_label'),
                    'data' => [
                        Territory::whereDoesntHave('assignments', $inCampaign)->count(),
                        Territory::whereHas('assignments', $inProgressInCampaign)->count(),
                        Territory::whereHas('assignments', $inCampaign)
                            ->whereDoesntHave('assignments', $inProgressInCampaign)
                            ->count(),
                    ],
                ],
            ],
            'labels' => [
                __('filament-resources.campaign.widget_assignments_chart_to_be_assigned'),
                __('filament-resources.campaign.widget_assignments_chart_in_progress'),
                __('filament-resources.campaign.widget_assignments_chart_completed'),
            ],
        ];
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('filament-resources.campaign.widget_assignments_chart_description');
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
