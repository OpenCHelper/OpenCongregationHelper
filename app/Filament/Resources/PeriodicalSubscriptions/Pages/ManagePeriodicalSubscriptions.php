<?php

namespace App\Filament\Resources\PeriodicalSubscriptions\Pages;

use App\Filament\Resources\PeriodicalSubscriptions\PeriodicalSubscriptionResource;
use App\Filament\Resources\PeriodicalSubscriptions\Widgets\PeriodicalTotals;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePeriodicalSubscriptions extends ManageRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PeriodicalSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('filament-resources.periodical_subscription.action_add'))
                ->modalHeading(__('filament-resources.periodical_subscription.action_add'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->createAnother(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PeriodicalTotals::class,
        ];
    }
}
