<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Laravel\Jetstream\Features;

class ApiTokens extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected string $view = 'filament.pages.api-tokens';

    protected static ?string $navigationLabel = 'API Tokens';

    /**
     * API tokens can only be managed when the Jetstream API feature is enabled.
     */
    public static function canAccess(): bool
    {
        return Features::hasApiFeatures();
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
