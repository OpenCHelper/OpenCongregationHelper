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

    public function badgeClass(): string
    {
        return match ($this) {
            self::PERSONAL => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
            self::EMERGENCY => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
            self::BUSINESS => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
            self::OTHER => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
        };
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
