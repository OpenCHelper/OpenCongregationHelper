<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\CampaignSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageCampaignSettings extends AppSettingsPage
{
    protected static string $settings = CampaignSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'campaigns';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.campaigns');
    }

    public function getTitle(): string
    {
        return __('settings.pages.campaigns');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->daysInput('expiring_days'),
                $this->daysInput('almost_expired_days')
                    ->lt('expiring_days'),
            ]);
    }

    /**
     * A field for a number of days before the end date of a campaign.
     */
    protected function daysInput(string $property): TextInput
    {
        return TextInput::make($property)
            ->label(__("settings.fields.{$property}.label"))
            ->helperText(__("settings.fields.{$property}.helper").' '.$this->defaultHint((string) $this->defaultValue($property)))
            ->integer()
            ->required()
            ->minValue(1)
            ->maxValue(365)
            ->suffix(__('settings.days'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_map('intval', parent::mutateFormDataBeforeSave($data));
    }
}
