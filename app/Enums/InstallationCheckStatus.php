<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The outcome of a check of the installation (see `App\Support\InstallationChecks`): an error means that the
 * application cannot work, a warning that a feature is off or degraded.
 */
enum InstallationCheckStatus: string implements HasColor, HasIcon, HasLabel
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';

    public function getLabel(): string
    {
        return __("installation.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Warning => 'warning',
            self::Error => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Ok => Heroicon::OutlinedCheckCircle,
            self::Warning => Heroicon::OutlinedExclamationTriangle,
            self::Error => Heroicon::OutlinedXCircle,
        };
    }
}
