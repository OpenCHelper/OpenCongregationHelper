<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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
                        Textarea::make('kingdom_hall_address')
                            ->label(__('settings.fields.kingdom_hall_address.label'))
                            ->placeholder(__('settings.fields.kingdom_hall_address.placeholder'))
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                Section::make(__('settings.sections.appearance'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('logo')
                            ->label(__('settings.fields.logo.label'))
                            ->helperText(__('settings.fields.logo.helper'))
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(self::LOGO_MAX_SIZE_KB),
                        ColorPicker::make('primary_color')
                            ->label(__('settings.fields.primary_color.label'))
                            ->helperText(__('settings.fields.primary_color.helper').' '.$this->defaultHint((string) config('ui-switcher.defaults.color')))
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                    ]),
            ]);
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
        if (filled($this->replacedLogo) && $this->replacedLogo !== app(GeneralSettings::class)->logo) {
            Storage::disk('public')->delete($this->replacedLogo);
        }
    }
}
