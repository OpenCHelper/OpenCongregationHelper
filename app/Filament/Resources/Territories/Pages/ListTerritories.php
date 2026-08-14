<?php

namespace App\Filament\Resources\Territories\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTerritories extends ListRecords
{
    protected static string $resource = TerritoryResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('all')
                ->label(__('filament-resources.territory.filter_territory_type_any'))
                ->badge(Territory::count()),
            'residential' => Tab::make('residential')
                ->label(__('filament-resources.territory.filter_territory_type_residential'))
                ->icon('heroicon-o-home')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_commercial', false))
                ->badge(Territory::query()->where('is_commercial', false)->count()),
            'commercial' => Tab::make('commercial')
                ->label(__('filament-resources.territory.filter_territory_type_commercial'))
                ->icon('heroicon-o-building-storefront')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_commercial', true))
                ->badge(Territory::query()->where('is_commercial', true)->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
