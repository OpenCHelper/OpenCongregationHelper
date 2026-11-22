<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\ResendInvitationAction;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Widgets\PublisherStatistics;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
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
                ->redirectTo('/'),
            EditAction::make()
                ->disabled(function () {
                    $record = $this->getRecord();
                    $currentUser = Auth::user();

                    return $record->isRootUserProtected() &&
                           ! ($currentUser instanceof User && $currentUser->isRootUser());
                })
                ->tooltip(function () {
                    $record = $this->getRecord();
                    $currentUser = Auth::user();

                    return $record->isRootUserProtected() &&
                           ! ($currentUser instanceof User && $currentUser->isRootUser())
                        ? __('profile.root_user_protection.edit_action_disabled')
                        : null;
                }),
            DeleteAction::make()
                ->visible(function () {
                    $record = $this->getRecord();

                    return ! $record->isRootUser();
                }),
        ];
    }
}
