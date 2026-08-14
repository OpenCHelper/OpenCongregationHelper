<?php

namespace App\Filament\Resources\Campaigns\Widgets;

use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class CampaignAssignmentsChart extends ChartWidget
{
    public ?Campaign $record = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('filament-resources.campaign.widget_assignments_chart_heading');
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('filament-resources.campaign.widget_assignments_chart_dataset_label'),
                    'data' => [
                        Territory::count() - $this->record->assignments()->count(),
                        $this->record->assignments()->whereNull('ended_at')->count(),
                        $this->record->assignments()->whereNotNull('ended_at')->count(),
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
