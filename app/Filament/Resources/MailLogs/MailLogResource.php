<?php

namespace App\Filament\Resources\MailLogs;

use App\Enums\NavigationGroup;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentMailLog\Resources\MailLogResource as BaseMailLogResource;

class MailLogResource extends BaseMailLogResource
{
    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::Developer->name;
    }

    public static function canAccess(): bool
    {
        return Auth::user()->can('view-logs');
    }
}
