<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The role of a member in a department.
 */
enum DepartmentRole: string implements HasColor, HasIcon, HasLabel
{
    // Change the order of the cases to update the order on the UI
    case Overseer = 'overseer';
    case Assistant = 'assistant';

    public function getLabel(): string
    {
        return __("departments.roles.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Overseer => 'primary',
            self::Assistant => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Overseer => Heroicon::OutlinedStar,
            self::Assistant => Heroicon::OutlinedUser,
        };
    }
}
