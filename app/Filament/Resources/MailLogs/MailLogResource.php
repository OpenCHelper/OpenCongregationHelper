<?php

namespace App\Filament\Resources\MailLogs;

use Illuminate\Support\Facades\Auth;
use \Tapp\FilamentMailLog\Resources\MailLogResource as BaseMailLogResource;

class MailLogResource extends BaseMailLogResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('filament-resources.navigation_groups.developer');
    }

    public static function canAccess(): bool
    {
        return Auth::user()->can('view-logs');
    }
}
