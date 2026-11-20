<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The steps of a request of a publication: ordering it from the branch is optional, since the copies may already be
 * in storage.
 */
enum PublicationRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    // Change the order of the cases to update the order on the UI
    case Requested = 'requested';
    case Ordered = 'ordered';
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return __("filament-resources.publication_request.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Ordered => 'info',
            self::Delivered => 'success',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Requested => Heroicon::OutlinedInboxArrowDown,
            self::Ordered => Heroicon::OutlinedTruck,
            self::Delivered => Heroicon::OutlinedCheckCircle,
        };
    }
}
