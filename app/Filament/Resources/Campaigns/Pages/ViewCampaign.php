<?php

namespace App\Filament\Resources\Campaigns\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Widgets\CampaignAssignmentsChart;
use App\Filament\Resources\Campaigns\Widgets\CampaignOverview;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public static function getNavigationLabel(): string
    {
        return ucfirst(__('filament-resources.campaign.model_label'));
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CampaignOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CampaignAssignmentsChart::class,
        ];
    }
    
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
