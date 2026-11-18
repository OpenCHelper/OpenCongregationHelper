<?php

namespace App\Support;

use App\Settings\ConfigSettings;
use App\Settings\GeneralSettings;
use App\Settings\TerritorySettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Apply the settings changed from the Settings pages over the configuration, which keeps the values of `.env`
 * for the settings never changed there (their defaults).
 */
class SettingsOverrides
{
    /**
     * The settings that replace configuration values.
     *
     * @var list<class-string<ConfigSettings>>
     */
    public const SETTINGS = [
        GeneralSettings::class,
        TerritorySettings::class,
    ];

    /**
     * The configuration values before the settings are applied, keyed by configuration key.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $defaults = null;

    /**
     * The default language applied last: `app.locale` follows the language of the user during the request.
     */
    protected ?string $appliedLocale = null;

    /**
     * Apply the settings over the configuration (and over the timezone and the locale, already set from it).
     *
     * Nothing is applied before the settings are migrated (e.g. during the installation).
     */
    public function apply(): void
    {
        $this->defaults ??= collect(self::SETTINGS)
            ->flatMap(fn (string $settings): array => array_values($settings::configKeys()))
            ->mapWithKeys(fn (string $configKey): array => [$configKey => config($configKey)])
            ->all();

        $userLocale = App::currentLocale();
        $previousLocale = $this->appliedLocale ?? $this->defaults['app.locale'];

        try {
            foreach (self::SETTINGS as $class) {
                $settings = app($class);

                foreach ($class::configKeys() as $property => $configKey) {
                    config([$configKey => $settings->{$property} ?? $this->defaults[$configKey]]);
                }
            }
        } catch (QueryException|MissingSettings) {
            return;
        }

        $this->appliedLocale = (string) config('app.locale');

        date_default_timezone_set((string) config('app.timezone'));

        // The language of the user, when they chose one (see `SwitchLanguageLocale`), wins over the default language
        App::setLocale($userLocale === $previousLocale ? $this->appliedLocale : $userLocale);
    }

    /**
     * Get the value that a setting has while it is not changed (from `.env`).
     *
     * @param  class-string<ConfigSettings>  $class
     */
    public function default(string $class, string $property): mixed
    {
        $configKey = $class::configKeys()[$property];

        return $this->defaults !== null && array_key_exists($configKey, $this->defaults)
            ? $this->defaults[$configKey]
            : config($configKey);
    }

    /**
     * Get the value that a setting has now: changed from the Settings pages, or its default.
     *
     * @param  class-string<ConfigSettings>  $class
     */
    public function current(string $class, string $property): mixed
    {
        return app($class)->{$property} ?? $this->default($class, $property);
    }
}
