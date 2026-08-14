<?php

namespace App\Filament\Resources\Campaigns\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    if (!isset($data['created_by']) || $data['created_by'] === null) {
                        $data['created_by'] = Auth::id();
                    }
                    return $data;
                }),
        ];
    }

}
