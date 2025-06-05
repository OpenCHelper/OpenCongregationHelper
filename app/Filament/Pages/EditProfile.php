<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class EditProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.edit-profile';

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('filament-resources.user_profile.navigation_label');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
}
