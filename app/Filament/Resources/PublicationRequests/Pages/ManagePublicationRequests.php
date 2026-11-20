<?php

namespace App\Filament\Resources\PublicationRequests\Pages;

use App\Enums\PublicationRequestStatus;
use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\PublicationRequests\Widgets\PublicationRequestsChart;
use App\Filament\Resources\PublicationRequests\Widgets\PublicationRequestsOverview;
use App\Models\PublicationRequest;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManagePublicationRequests extends ManageRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PublicationRequestResource::class;

    /**
     * The name of the menu item, not the plural model label in title case.
     */
    public function getTitle(): string|Htmlable
    {
        return PublicationRequestResource::getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('filament-resources.publication_request.action_create'))
                ->modalHeading(__('filament-resources.publication_request.action_create'))
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother()
                ->using(fn (array $data): Model => PublicationRequestResource::createFromForm($data)),
        ];
    }

    public function getTabs(): array
    {
        return [
            'to_order' => Tab::make('to_order')
                ->label(__('filament-resources.publication_request.tab_to_order'))
                ->icon(PublicationRequestStatus::Requested->getIcon())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->toOrder())
                ->badge(PublicationRequest::query()->toOrder()->count() ?: null)
                ->badgeColor(PublicationRequestStatus::Requested->getColor()),
            'ordered' => Tab::make('ordered')
                ->label(__('filament-resources.publication_request.tab_ordered'))
                ->icon(PublicationRequestStatus::Ordered->getIcon())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->ordered())
                ->badge(PublicationRequest::query()->ordered()->count() ?: null)
                ->badgeColor(PublicationRequestStatus::Ordered->getColor()),
            'delivered' => Tab::make('delivered')
                ->label(__('filament-resources.publication_request.tab_delivered'))
                ->icon(PublicationRequestStatus::Delivered->getIcon())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->delivered()),
            'all' => Tab::make('all')
                ->label(__('filament-resources.publication_request.tab_all')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PublicationRequestsOverview::class,
            PublicationRequestsChart::class,
        ];
    }
}
