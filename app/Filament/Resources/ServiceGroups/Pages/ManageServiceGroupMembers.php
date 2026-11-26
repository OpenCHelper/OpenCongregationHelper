<?php

namespace App\Filament\Resources\ServiceGroups\Pages;

use App\Filament\Resources\ServiceGroups\ServiceGroupResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ServiceGroup;
use App\Models\User;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

class ManageServiceGroupMembers extends ManageRelatedRecords
{
    protected static string $resource = ServiceGroupResource::class;

    protected static string $relationship = 'members';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_service_group_members');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Livewire::current()->getRecord()->members()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (User $record): string => $record->getFullName())
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('first_name')
                    ->label(__('filament-resources.user.column_first_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label(__('filament-resources.user.column_last_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role_in_group')
                    ->label(__('filament-resources.service_group.column_role_in_group'))
                    ->state(fn (User $record): ?string => static::getRoleInGroup($this->getRecord(), $record))
                    ->badge(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('filament-resources.service_group.action_add_members'))
                    ->modalHeading(__('filament-resources.service_group.action_add_members'))
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['first_name', 'last_name'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->orderBy('first_name')->orderBy('last_name')),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label(__('filament-resources.service_group.action_remove_member')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make()
                        ->label(__('filament-resources.service_group.action_remove_members')),
                ]),
            ]);
    }

    /**
     * The role of the user in the group, if they lead it.
     */
    public static function getRoleInGroup(ServiceGroup $serviceGroup, User $user): ?string
    {
        return match ($user->getKey()) {
            $serviceGroup->coordinator_id => __('filament-resources.service_group.role_coordinator'),
            $serviceGroup->assistant_coordinator1_id, $serviceGroup->assistant_coordinator2_id => __('filament-resources.service_group.role_assistant'),
            default => null,
        };
    }
}
