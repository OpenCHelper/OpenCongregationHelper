<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\ConfigSettingsPage;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneralSettings extends ConfigSettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'general';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.general');
    }

    public function getTitle(): string
    {
        return __('settings.pages.general');
    }

    public function form(Schema $schema): Schema
    {
        /** @var array<string, string> $locales */
        $locales = config('app.available_locales');
        $timezones = timezone_identifiers_list();

        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('settings.fields.name.label'))
                    ->helperText($this->defaultHint((string) $this->defaultValue('name')))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('locale')
                    ->label(__('settings.fields.locale.label'))
                    ->helperText($this->defaultHint($locales[$this->defaultValue('locale')] ?? (string) $this->defaultValue('locale')))
                    ->options($locales)
                    ->required()
                    ->native(false),
                Select::make('timezone')
                    ->label(__('settings.fields.timezone.label'))
                    ->helperText($this->defaultHint((string) $this->defaultValue('timezone')))
                    ->options(array_combine($timezones, $timezones))
                    ->required()
                    ->searchable(),
            ]);
    }
}
