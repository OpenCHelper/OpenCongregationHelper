<?php

namespace App\Filament\Resources\Activitylogs;

use App\Enums\NavigationGroup;
use Illuminate\Support\Facades\Auth;
use \Rmsramos\Activitylog\Resources\Activitylog\ActivitylogResource as BaseActivitylogResource;

class ActivitylogResource extends BaseActivitylogResource
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
