<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use FilipFonal\FilamentLogManager\Pages\Logs as BaseLogsPage;
use Override;

class Logs extends BaseLogsPage
{
    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return config('filament-log-manager.navigation_group') ? NavigationGroup::Developer->name : null;
    }
}
