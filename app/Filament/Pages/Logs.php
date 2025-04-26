<?php

namespace App\Filament\Pages;

use FilipFonal\FilamentLogManager\Pages\Logs as BaseLogsPage;

class Logs extends BaseLogsPage
{
    public static function getNavigationGroup(): ?string
    {
        return config('filament-log-manager.navigation_group') ? 'Developer' : null;
    }
}
