<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\NotificationSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageNotificationSettings extends AppSettingsPage
{
    protected static string $settings = NotificationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'notifications';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.notifications');
    }

    public function getTitle(): string
    {
        return __('settings.pages.notifications');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mail_from_name')
                    ->label(__('settings.fields.mail_from_name.label'))
                    ->helperText(__('settings.fields.mail_from_name.helper').' '.$this->defaultHint((string) $this->defaultValue('mail_from_name')))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
