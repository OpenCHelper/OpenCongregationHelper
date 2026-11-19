<?php

namespace App\Filament\Resources\PublicationSubscribers\Pages;

use App\Filament\Resources\PublicationSubscribers\PublicationSubscriberResource;
use App\Filament\Resources\PublicationSubscribers\Widgets\PublicationTotals;
use App\Filament\Resources\Publications\PublicationResource;
use App\Models\Publication;
use App\Models\PublicationSubscriber;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ManagePublicationSubscribers extends ManageRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PublicationSubscriberResource::class;

    /**
     * The name of the menu item, not the plural model label in title case ("Paper Periodicals").
     */
    public function getTitle(): string|Htmlable
    {
        return PublicationSubscriberResource::getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('filament-resources.publication_subscriber.action_add'))
                ->modalHeading(__('filament-resources.publication_subscriber.action_add'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->createAnother()
                // A publisher removed from the list is added again with the same record, to keep their history
                ->using(fn (array $data): Model => tap(
                    PublicationSubscriber::query()->firstOrNew(['user_id' => $data['user_id']]),
                    fn (PublicationSubscriber $subscriber) => $subscriber->fill($data)->save(),
                ))
                // Nothing can be received until a publication is offered
                ->disabled(fn (): bool => Publication::offeredRenewable()->isEmpty())
                ->tooltip(fn (): ?string => Publication::offeredRenewable()->isEmpty()
                    ? __('filament-resources.publication_subscriber.no_publications', ['list' => PublicationResource::getNavigationLabel()])
                    : null),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PublicationTotals::class,
        ];
    }
}
