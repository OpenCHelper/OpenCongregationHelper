<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\CampaignResource\Widgets\CampaignAssignmentsChart;
use App\Filament\Resources\CampaignResource\Widgets\CampaignOverview;
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
            Actions\EditAction::make(),
        ];
    }
}
