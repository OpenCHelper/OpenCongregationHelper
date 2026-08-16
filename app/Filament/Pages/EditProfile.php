<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class EditProfile extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.edit-profile';

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
