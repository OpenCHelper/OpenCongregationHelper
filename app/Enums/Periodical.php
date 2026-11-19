<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The periodicals that the publishers can receive on paper.
 */
enum Periodical: string implements HasColor, HasLabel
{
    // Change the order of the cases to update the order on the UI
    case Watchtower = 'watchtower';
    case WatchtowerLargePrint = 'watchtower_large_print';
    case MeetingWorkbook = 'meeting_workbook';

    public function getLabel(): string
    {
        return __("filament-resources.periodical_subscription.periodicals.{$this->value}");
    }

    /**
     * The short name of the periodical, for the headers of the columns.
     */
    public function getShortLabel(): string
    {
        return __("filament-resources.periodical_subscription.periodicals_short.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Watchtower => 'info',
            self::WatchtowerLargePrint => 'primary',
            self::MeetingWorkbook => 'warning',
        };
    }
}
