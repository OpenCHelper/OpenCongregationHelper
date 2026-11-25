<?php

namespace App\Filament\Resources\Campaigns\Widgets;

use App\Models\Campaign;
use App\Models\Territory;
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
                    // The gray, warning (amber) and success (green) colors of the theme, readable in light and dark mode
                    'backgroundColor' => [
                        'rgba(156, 163, 175, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
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
