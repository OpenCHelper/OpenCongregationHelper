<?php

namespace App\Filament\Resources\Campaigns\Widgets;

use App\Models\Campaign;
use App\Models\Territory;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class CampaignOverview extends BaseWidget
{
    public ?Campaign $record = null;

    protected function getStats(): array
    {
        // Take all assignments into consideration, even unfinished ones.
        $campaign_percentage = $this->record->assignments()->count() * 100 / Territory::count();
        $campaign_remaining_days = $this->record->valid_until ? - $this->record->valid_until->diffInDays(now(), false) : null;
        $campaign_remaining_time_percentage = $this->record->valid_from && $this->record->valid_until
            ? now()->floatDiffInDays($this->record->valid_from) * 100 / $this->record->valid_from->floatDiffInDays($this->record->valid_until)
            : null;

        $color = 'success';
        if ($campaign_remaining_days !== null && $campaign_remaining_days < 0) {
            $description = __('filament-resources.campaign.widget_campaign_expired', ['days' => abs(round($campaign_remaining_days))]);

            if ($campaign_percentage < 100) {
                $color = 'danger';
                $icon = 'heroicon-s-x-circle';
            }
        } elseif ($campaign_remaining_days !== null) {
            $description = __('filament-resources.campaign.widget_days_remaining', ['days' => $campaign_remaining_days]);

            if ($campaign_remaining_time_percentage !== null) {
                if ($campaign_remaining_time_percentage < 25 && $campaign_percentage < 25) {
                    $color = 'danger';
                    $description = __('filament-resources.campaign.widget_warning_25_percent', ['percentage' => round($campaign_percentage, 2)]);
                } elseif ($campaign_remaining_time_percentage < 50 && $campaign_percentage < 50) {
                    $color = 'warning';
                    $description = __('filament-resources.campaign.widget_warning_50_percent', ['percentage' => round($campaign_percentage, 2)]);
                } elseif ($campaign_remaining_time_percentage < 75 && $campaign_percentage < 75) {
                    $color = 'warning';
                    $description = __('filament-resources.campaign.widget_warning_75_percent', ['percentage' => round($campaign_percentage, 2)]);
                }
            }
        }

        return [
            Stat::make(__('filament-resources.campaign.widget_remaining_days'), fn () => $campaign_remaining_days === null ? '∞' : max(0, round($campaign_remaining_days)))
                ->description($this->record->valid_until ? __('filament-resources.campaign.widget_valid_until', ['date' => $this->record->valid_until->toDateString()]) : __('filament-resources.campaign.widget_no_end_date'))
                ->color($this->record->valid_until && $this->record->valid_until->isPast() ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedCalendarDays),
            Stat::make(__('filament-resources.campaign.widget_completion_status'), fn () => Number::percentage($campaign_percentage))
                ->description($description ?? '')
                ->descriptionIcon($icon ?? '')
                ->color($color)
                ->icon(Heroicon::OutlinedFlag),
        ];
    }
}
