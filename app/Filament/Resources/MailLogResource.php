<?php

namespace App\Filament\Resources;

use Auth;
use \Tapp\FilamentMailLog\Resources\MailLogResource as BaseMailLogResource;

class MailLogResource extends BaseMailLogResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Developer';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->can('view-logs');
    }
}
