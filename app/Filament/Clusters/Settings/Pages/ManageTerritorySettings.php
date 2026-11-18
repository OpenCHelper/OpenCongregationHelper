<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\ConfigSettingsPage;
use App\Settings\TerritorySettings;
use BackedEnum;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageTerritorySettings extends ConfigSettingsPage
{
    protected static string $settings = TerritorySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'territories';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.territories');
    }

    public function getTitle(): string
    {
        return __('settings.pages.territories');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('map_default_center')
                    ->label(__('settings.fields.map_default_center.label'))
                    ->placeholder('41.159063, -74.255188')
                    ->helperText(__('settings.fields.map_default_center.helper').' '.$this->defaultHint(
                        self::formatCoordinates($this->defaultValue('map_default_center')),
                    ))
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (self::parseCoordinates((string) $value) === null) {
                            $fail(__('settings.fields.map_default_center.invalid'));
                        }
                    })
                    ->columnSpanFull(),
                Toggle::make('shared_pii')
                    ->label(__('settings.fields.shared_pii.label'))
                    ->helperText(__('settings.fields.shared_pii.helper').' '.$this->defaultHint(
                        $this->defaultValue('shared_pii') ? __('settings.on') : __('settings.off'),
                    ))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['map_default_center'] = self::formatCoordinates($data['map_default_center']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['map_default_center'] = self::parseCoordinates((string) $data['map_default_center']);
        $data['shared_pii'] = (bool) $data['shared_pii'];

        return parent::mutateFormDataBeforeSave($data);
    }

    /**
     * Parse coordinates written as "latitude, longitude", if they are valid.
     *
     * @return array{float, float}|null
     */
    public static function parseCoordinates(string $value): ?array
    {
        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return null;
        }

        [$latitude, $longitude] = [(float) $parts[0], (float) $parts[1]];

        if (abs($latitude) > 90 || abs($longitude) > 180) {
            return null;
        }

        return [$latitude, $longitude];
    }

    /**
     * Write coordinates as "latitude, longitude".
     *
     * @param  array{float, float}  $coordinates
     */
    protected static function formatCoordinates(array $coordinates): string
    {
        return implode(', ', $coordinates);
    }
}
