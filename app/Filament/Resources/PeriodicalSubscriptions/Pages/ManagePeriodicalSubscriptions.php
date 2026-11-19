<?php

namespace App\Filament\Resources\PeriodicalSubscriptions\Pages;

use App\Filament\Resources\PeriodicalSubscriptions\PeriodicalSubscriptionResource;
use App\Filament\Resources\PeriodicalSubscriptions\Widgets\PeriodicalTotals;
use App\Filament\Resources\Publications\PublicationResource;
use App\Models\Publication;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ManagePeriodicalSubscriptions extends ManageRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PeriodicalSubscriptionResource::class;

    /**
     * The name of the menu item, not the plural model label in title case ("Paper Periodicals").
     */
    public function getTitle(): string|Htmlable
    {
        return PeriodicalSubscriptionResource::getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('filament-resources.periodical_subscription.action_add'))
                ->modalHeading(__('filament-resources.periodical_subscription.action_add'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->createAnother()
                ->using(fn (array $data): Model => PeriodicalSubscriptionResource::saveRecord(null, $data))
                // Nothing can be received until a publication is offered
                ->disabled(fn (): bool => Publication::offered()->isEmpty())
                ->tooltip(fn (): ?string => Publication::offered()->isEmpty()
                    ? __('filament-resources.periodical_subscription.no_publications', ['list' => PublicationResource::getNavigationLabel()])
                    : null),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PeriodicalTotals::class,
        ];
    }
}
