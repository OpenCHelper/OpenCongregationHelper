<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use STS\FilamentImpersonate\Pages\Actions\Impersonate;
use Illuminate\Support\Facades\Auth;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.view_user');
    }

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()
                ->record($this->getRecord())
                ->redirectTo('/'),
            Actions\EditAction::make()
                ->disabled(function () {
                    $record = $this->getRecord();
                    $currentUser = Auth::user();
                    return $record->isRootUserProtected() && 
                           !($currentUser instanceof User && $currentUser->isRootUser());
                })
                ->tooltip(function () {
                    $record = $this->getRecord();
                    $currentUser = Auth::user();
                    return $record->isRootUserProtected() && 
                           !($currentUser instanceof User && $currentUser->isRootUser())
                        ? __('profile.root_user_protection.edit_action_disabled')
                        : null;
                }),
            Actions\DeleteAction::make()
                ->visible(function () {
                    $record = $this->getRecord();
                    return !$record->isRootUser();
                }),
        ];
    }
}
