<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
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
                ->icon(Heroicon::OutlinedHome)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_commercial', false))
                ->badge(Territory::query()->where('is_commercial', false)->count()),
            'commercial' => Tab::make('commercial')
                ->label(__('filament-resources.territory.filter_territory_type_commercial'))
                ->icon(Heroicon::OutlinedBuildingStorefront)
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
