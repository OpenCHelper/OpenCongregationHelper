<?php

namespace App\Filament\Clusters\Settings;

use App\Settings\ConfigSettings;
use App\Support\SettingsOverrides;
use Filament\Pages\SettingsPage;

/**
 * A Settings page of settings that replace configuration values: the form shows the default (from `.env`) of the
 * settings never changed, and a value set back to its default is stored as `null`, so that it follows `.env` again.
 */
abstract class ConfigSettingsPage extends SettingsPage
{
    protected static ?string $cluster = SettingsCluster::class;

    public static function canAccess(): bool
    {
        return SettingsCluster::canAccess();
    }

    /**
     * @return class-string<ConfigSettings>
     */
    public static function getSettings(): string
    {
        return static::$settings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (array_intersect_key($data, static::getSettings()::configKeys()) as $property => $value) {
            $data[$property] = $value ?? $this->defaultValue($property);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (array_intersect_key($data, static::getSettings()::configKeys()) as $property => $value) {
            if ($value === $this->defaultValue($property)) {
                $data[$property] = null;
            }
        }

        return $data;
    }

    /**
     * Apply the saved settings to the rest of the request too.
     */
    protected function afterSave(): void
    {
        app(SettingsOverrides::class)->apply();
    }

    /**
     * Get the default value of a setting, from `.env`.
     */
    protected function defaultValue(string $property): mixed
    {
        $settings = static::getSettings();

        return array_key_exists($property, $settings::configKeys())
            ? app(SettingsOverrides::class)->default($settings, $property)
            : $settings::defaultValue($property);
    }

    /**
     * Describe the default value of a setting, below its field.
     */
    protected function defaultHint(string $value): string
    {
        return __('settings.default', ['value' => $value]);
    }
}
