<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('all')
                ->label(__('filament-resources.user.tab_all'))
                ->badge(UserResource::getEloquentQuery()->count()),
            'with_access' => Tab::make('with_access')
                ->label(__('filament-resources.user.tab_with_access'))
                ->icon(Heroicon::OutlinedUser)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('can_login', true))
                ->badge(UserResource::getEloquentQuery()->where('can_login', true)->count()),
            'virtual' => Tab::make('virtual')
                ->label(__('filament-resources.user.tab_virtual'))
                ->icon(Heroicon::OutlinedUserMinus)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('can_login', false))
                ->badge(UserResource::getEloquentQuery()->where('can_login', false)->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
