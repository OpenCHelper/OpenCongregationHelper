<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum NavigationGroup: string implements HasLabel
{
    // Change the order of the cases to update the order on the UI
    case Territories = 'territories';
    case Administration = 'administration';
    case Developer = 'developer';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Developer => __('navigation.groups.developer'),
            self::Administration => __('navigation.groups.administration'),
            self::Territories => __('navigation.groups.territories'),
        };
    }
}
