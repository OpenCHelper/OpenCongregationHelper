<?php

namespace App\Filament\Resources\Activitylogs;

use Auth;
use \Rmsramos\Activitylog\Resources\Activitylog\ActivitylogResource as BaseActivitylogResource;

class ActivitylogResource extends BaseActivitylogResource
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
