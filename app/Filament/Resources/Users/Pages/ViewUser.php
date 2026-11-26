<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\DeleteUserAction;
use App\Filament\Resources\Users\Actions\ResendInvitationAction;
use App\Filament\Resources\Users\Actions\TransferRootAction;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Widgets\PublisherStatistics;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.view_user');
    }

    protected function getFooterWidgets(): array
    {
        return PublisherStatistics::canViewStatisticsOf($this->getRecord())
            ? [PublisherStatistics::class]
            : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            ResendInvitationAction::make(),
            Impersonate::make()
                ->record($this->getRecord())
                ->requiresConfirmation()
                ->redirectTo('/'),
            // The policy denies editing the protected root user with a message, shown as the tooltip
            EditAction::make()
                ->authorizationTooltip(),
            TransferRootAction::make(),
            DeleteUserAction::make(),
        ];
    }
}
