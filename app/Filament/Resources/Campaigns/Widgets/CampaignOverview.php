<?php

namespace App\Filament\Resources\Campaigns\Widgets;

use App\Models\Campaign;
use App\Models\Territory;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class CampaignOverview extends BaseWidget
{
    public ?Campaign $record = null;

    protected function getStats(): array
    {
        // Take all assignments into consideration, even unfinished ones, but count each territory once (a territory may
        // be assigned more than once), and only the territories that still exist, like the assignments chart.
        $territories_count = Territory::count();
        $assigned_territories_count = Territory::whereHas(
            'assignments',
            fn (Builder $query): Builder => $query->where('campaign_id', $this->record->id),
        )->count();
        $campaign_percentage = $territories_count > 0 ? $assigned_territories_count * 100 / $territories_count : 0;

        $campaign_remaining_days = $this->record->valid_until ? now()->diffInDays($this->record->valid_until) : null;

        // Percentage of the campaign duration that still remains (Carbon 3 differences are signed)
        $campaign_duration_days = $this->record->valid_from && $this->record->valid_until
            ? $this->record->valid_from->diffInDays($this->record->valid_until)
            : null;
        $campaign_remaining_time_percentage = $campaign_duration_days > 0
            ? $campaign_remaining_days * 100 / $campaign_duration_days
            : null;

        $color = 'success';
        if ($campaign_remaining_days !== null && $campaign_remaining_days < 0) {
            $description = __('filament-resources.campaign.widget_campaign_expired', ['days' => abs(round($campaign_remaining_days))]);

            if ($campaign_percentage < 100) {
                $color = 'danger';
                $icon = 'heroicon-s-x-circle';
            }
        } elseif ($campaign_remaining_days !== null) {
            $description = __('filament-resources.campaign.widget_days_remaining', ['days' => round($campaign_remaining_days)]);

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
