<?php

namespace App\Filament\Clusters\Settings;

use App\Settings\AppSettings;
use App\Settings\ConfigSettings;
use App\Support\SettingsOverrides;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\View;
use Illuminate\Support\HtmlString;

/**
 * A Settings page, shown only to the super administrators.
 *
 * For the settings that replace configuration values (see `ConfigSettings`), the form shows the default (from `.env`)
 * of the settings never changed, and a value set back to its default is stored as `null`, so that it follows `.env`
 * again.
 */
abstract class AppSettingsPage extends SettingsPage
{
    protected static ?string $cluster = SettingsCluster::class;

    public static function canAccess(): bool
    {
        return SettingsCluster::canAccess();
    }

    /**
     * @return class-string<AppSettings>
     */
    public static function getSettings(): string
    {
        return static::$settings;
    }

    /**
     * The configuration replaced by the settings of the page, keyed by property.
     *
     * @return array<string, string>
     */
    protected static function getConfigKeys(): array
    {
        $settings = static::getSettings();

        return is_subclass_of($settings, ConfigSettings::class) ? $settings::configKeys() : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (array_intersect_key($data, static::getConfigKeys()) as $property => $value) {
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
        foreach (array_intersect_key($data, static::getConfigKeys()) as $property => $value) {
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
     * Get the default value of a setting: from `.env` for the settings replacing configuration values.
     */
    protected function defaultValue(string $property): mixed
    {
        $settings = static::getSettings();

        return array_key_exists($property, static::getConfigKeys())
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

    /**
     * A stage of a sequence of thresholds (e.g. the statuses of a campaign) without a value of its own, shown as a row
     * among the fields of the thresholds.
     */
    protected function stageRow(string $name, string $color, string $note): View
    {
        return View::make('filament.settings.threshold-stage')
            ->viewData(['name' => $name, 'color' => $color, 'note' => $note])
            ->columnSpanFull();
    }

    /**
     * The field of the threshold from which a stage starts, shown as a row with the colour of the stage.
     */
    protected function stageInput(string $property, string $name, string $color, string $unit): TextInput
    {
        return TextInput::make($property)
            ->label(new HtmlString('<span class="fi-settings-stage-dot"></span>'.e($name)))
            ->validationAttribute($name)
            ->inlineLabel()
            ->helperText($this->defaultHint((string) $this->defaultValue($property)))
            ->integer()
            ->required()
            ->suffix($unit)
            ->extraFieldWrapperAttributes([
                'class' => 'fi-settings-stage',
                'style' => "--stage-color: {$color}",
            ])
            ->columnSpanFull();
    }

    /**
     * A bar showing the stages to scale, updated while the thresholds are typed.
     *
     * The stages without a field are open-ended (e.g. before the first threshold), and get a fixed width.
     *
     * @param  list<array{name: string, color: string, field?: string}>  $stages
     * @param  bool  $isCountdown  Whether the thresholds are days before an end (decreasing), instead of days after a start
     */
    protected function stagesBar(array $stages, bool $isCountdown, string $startLabel, string $endLabel): View
    {
        return View::make('filament.settings.thresholds-bar')
            ->viewData([
                'stages' => $stages,
                'isCountdown' => $isCountdown,
                'startLabel' => $startLabel,
                'endLabel' => $endLabel,
            ])
            ->columnSpanFull();
    }
}
