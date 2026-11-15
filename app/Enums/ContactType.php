<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactType: string implements HasColor, HasLabel
{
    case PERSONAL = 'personal';
    case EMERGENCY = 'emergency';
    case BUSINESS = 'business';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PERSONAL => __('profile.contacts.types.personal'),
            self::EMERGENCY => __('profile.contacts.types.emergency'),
            self::BUSINESS => __('profile.contacts.types.business'),
            self::OTHER => __('profile.contacts.types.other'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PERSONAL => 'success',
            self::EMERGENCY => 'danger',
            self::BUSINESS => 'warning',
            self::OTHER => 'gray',
        };
    }
}
