<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\SecuritySettings;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSecuritySettings extends AppSettingsPage
{
    protected static string $settings = SecuritySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'security';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.security');
    }

    public function getTitle(): string
    {
        return __('settings.pages.security');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.sections.account_requirements'))
                    ->description(__('settings.sections.account_requirements_description'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('require_two_factor')
                            ->label(__('settings.fields.require_two_factor.label'))
                            ->helperText(__('settings.fields.require_two_factor.helper')),
                        Toggle::make('require_email_verification')
                            ->label(__('settings.fields.require_email_verification.label'))
                            ->helperText(__('settings.fields.require_email_verification.helper')),
                    ]),
            ]);
    }
}
