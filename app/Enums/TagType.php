<?php

namespace App\Enums;

/**
 * The tag types used in the application.
 *
 * @see https://spatie.be/docs/laravel-tags/v4/advanced-usage/using-types
 */
enum TagType: string
{
    case TERRITORIES = 'territories';
    case ZONES = 'zones';

    public function getLabel(): string
    {
        return match ($this) {
            self::TERRITORIES => __('filament-resources.tag.type_territories'),
            self::ZONES => __('filament-resources.tag.type_zones'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TERRITORIES => 'success',
            self::ZONES => 'info',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
