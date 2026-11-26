<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ManageGeneralSettings extends AppSettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'general';

    /**
     * Largest logo that can be uploaded, in kilobytes.
     */
    public const LOGO_MAX_SIZE_KB = 2048;

    /**
     * Longest name of the application, so that it fits the top bar and the name of the installed app.
     */
    public const APP_NAME_MAX_LENGTH = 50;

    /**
     * The logo stored before saving, deleted when it is replaced or removed.
     */
    protected ?string $replacedLogo = null;

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
        return $schema
            ->components([
                Section::make(__('settings.sections.congregation'))
                    ->columnSpanFull()
                    ->schema([
                        self::appNameField(),
                        self::kingdomHallAddressField(),
                    ]),
                Section::make(__('settings.sections.appearance'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        self::logoField(),
                        self::primaryColorField(),
                    ]),
            ]);
    }

    /**
     * The fields below are shared with the first-run setup wizard (`SetupWizard`).
     */
    public static function appNameField(): TextInput
    {
        return TextInput::make('app_name')
            ->label(__('settings.fields.app_name.label'))
            ->helperText(__('settings.fields.app_name.helper'))
            ->required()
            ->maxLength(self::APP_NAME_MAX_LENGTH);
    }

    public static function kingdomHallAddressField(): Textarea
    {
        return Textarea::make('kingdom_hall_address')
            ->label(__('settings.fields.kingdom_hall_address.label'))
            ->placeholder(__('settings.fields.kingdom_hall_address.placeholder'))
            ->rows(3)
            ->maxLength(500);
    }

    public static function logoField(): FileUpload
    {
        return FileUpload::make('logo')
            ->label(__('settings.fields.logo.label'))
            ->helperText(__('settings.fields.logo.helper'))
            ->image()
            ->disk('public')
            ->directory('branding')
            ->visibility('public')
            ->maxSize(self::LOGO_MAX_SIZE_KB);
    }

    public static function primaryColorField(): ColorPicker
    {
        return ColorPicker::make('primary_color')
            ->label(__('settings.fields.primary_color.label'))
            ->helperText(__('settings.fields.primary_color.helper').' '.__('settings.default', ['value' => (string) config('ui-switcher.defaults.color')]))
            ->regex('/^#[0-9a-fA-F]{6}$/');
    }

    /**
     * Delete the file of a logo once it is replaced or removed.
     */
    public static function deleteReplacedLogo(?string $replacedLogo, ?string $currentLogo): void
    {
        if (filled($replacedLogo) && $replacedLogo !== $currentLogo) {
            Storage::disk('public')->delete($replacedLogo);
        }
    }

    /**
     * Remember the logo being replaced, to delete its file once the new one is saved.
     */
    protected function beforeSave(): void
    {
        $this->replacedLogo = app(GeneralSettings::class)->logo;
    }

    protected function afterSave(): void
    {
        self::deleteReplacedLogo($this->replacedLogo, app(GeneralSettings::class)->logo);
    }
}
